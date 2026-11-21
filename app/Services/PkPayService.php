<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PKPay (pkpay.site) Raast-QR gateway client.
 *
 * Requests are signed with HMAC-SHA256 over  ts\nMETHOD\nURI\nRAW_BODY.
 * Callbacks are signed over  ts . "." . RAW_BODY.  The API secret stays on
 * the server and is never sent to the browser or logged.
 */
class PkPayService
{
    public function enabled(): bool
    {
        return setting('pkpay_enabled', '0') === '1' && $this->configured();
    }

    public function configured(): bool
    {
        return $this->key() !== '' && $this->secret() !== '';
    }

    private function baseUrl(): string
    {
        return rtrim((string) setting('pkpay_base_url', 'https://pkpay.site/api/v1'), '/');
    }

    private function key(): string
    {
        return (string) setting('pkpay_api_key', '');
    }

    private function secret(): string
    {
        return (string) setting('pkpay_api_secret', '');
    }

    /**
     * Create a pending payment at the gateway.
     *
     * @return array{payment_id:string, transaction_id:string, checkout_url:string, short_url:?string, status:string, amount:string, expires_at:?string, ...}
     */
    public function createPayment(array $payload, string $idempotencyKey): array
    {
        $res = $this->request('POST', '/payments', $payload, $idempotencyKey);

        return $res['data'] ?? throw new RuntimeException($this->errorMessage($res));
    }

    /** Fetch the latest status for a gateway payment id. */
    public function getPayment(string $gatewayPaymentId): array
    {
        $res = $this->request('GET', '/payments/'.rawurlencode($gatewayPaymentId));

        return $res['data'] ?? throw new RuntimeException($this->errorMessage($res));
    }

    /** Used by the "Test connection" button in admin. */
    public function balance(): array
    {
        $res = $this->request('GET', '/balance');

        return $res['data'] ?? throw new RuntimeException($this->errorMessage($res));
    }

    /** Verify a webhook callback: signature = HMAC(ts . "." . rawBody). */
    public function verifyCallback(?string $timestamp, string $rawBody, ?string $signature): bool
    {
        if (! $timestamp || ! $signature || ! $this->configured()) {
            return false;
        }
        if (abs(time() - (int) $timestamp) > 600) {
            return false;   // stale
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$rawBody, $this->secret());

        return hash_equals($expected, $signature);
    }

    private function request(string $method, string $path, ?array $payload = null, ?string $idempotencyKey = null): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Payment gateway abhi set nahi hai.');
        }

        $url = $this->baseUrl().$path;
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ts = (string) time();
        $uri = parse_url($url, PHP_URL_PATH).(parse_url($url, PHP_URL_QUERY) ? '?'.parse_url($url, PHP_URL_QUERY) : '');
        $signature = hash_hmac('sha256', $ts."\n".$method."\n".$uri."\n".$body, $this->secret());

        $headers = [
            'Accept' => 'application/json',
            'X-Api-Key' => $this->key(),
            'X-Timestamp' => $ts,
            'X-Signature' => $signature,
        ];
        if ($method === 'POST') {
            $headers['Idempotency-Key'] = $idempotencyKey ?: throw new RuntimeException('Idempotency key missing.');
            $headers['Content-Type'] = 'application/json';
        }

        $req = Http::withHeaders($headers)->timeout(20);
        $response = $method === 'POST'
            ? $req->withBody($body, 'application/json')->post($url)
            : $req->get($url);

        $json = $response->json() ?? [];

        if ($response->failed()) {
            throw new RuntimeException($this->errorMessage($json, $response->status()), $response->status());
        }

        return $json;
    }

    private function errorMessage(?array $json, ?int $status = null): string
    {
        $code = $json['error']['code'] ?? null;
        $map = [
            'ip_not_allowed' => 'Server ka IP gateway ki allowlist mein nahi hai. PKPay admin ko apne server ka IP dein.',
            'merchant_disabled' => 'Merchant account disabled hai.',
            'payin_disabled' => 'Gateway par pay-in abhi band hai.',
            'invalid_signature' => 'Signature match nahi hui — API secret check karein.',
            'unauthenticated' => 'API key ghalat hai.',
            'rate_limited' => 'Bohat zyada requests — thori dair baad koshish karein.',
            'no_account_available' => 'Abhi koi payment account available nahi — thori dair baad koshish karein.',
        ];

        return $map[$code]
            ?? ($json['error']['message'] ?? 'Gateway error'.($status ? " (HTTP {$status})" : '').'.');
    }
}
