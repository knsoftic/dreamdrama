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
    /** The gateway is usable as soon as its credentials are configured. */
    public function enabled(): bool
    {
        return $this->configured();
    }

    /** What users see at checkout: 'manual', 'gateway' (auto) or 'both'. */
    public function mode(): string
    {
        $mode = setting('payment_mode', 'manual');

        // A gateway mode only takes effect once credentials are set; otherwise
        // fall back to manual so purchases never get stuck.
        if ($mode !== 'manual' && ! $this->configured()) {
            return 'manual';
        }

        return $mode;
    }

    public function showsGateway(): bool
    {
        return $this->mode() !== 'manual';
    }

    public function showsManual(): bool
    {
        return $this->mode() !== 'gateway';
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
            throw new RuntimeException('The payment gateway is not set up yet.');
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
            'ip_not_allowed' => 'The server IP is not in the gateway allowlist. Give your server IP to the PKPay admin.',
            'merchant_disabled' => 'The merchant account is disabled.',
            'payin_disabled' => 'Pay-in is currently disabled on the gateway.',
            'invalid_signature' => 'Signature did not match — check the API secret.',
            'unauthenticated' => 'The API key is incorrect.',
            'rate_limited' => 'Too many requests — please try again shortly.',
            'no_account_available' => 'No payment account is available right now — please try again shortly.',
        ];

        return $map[$code]
            ?? ($json['error']['message'] ?? 'Gateway error'.($status ? " (HTTP {$status})" : '').'.');
    }
}
