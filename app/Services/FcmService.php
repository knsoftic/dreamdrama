<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends push notifications through Firebase Cloud Messaging (HTTP v1).
 *
 * The admin pastes a Firebase service-account JSON (Settings are saved under
 * `fcm_service_account`). From it we mint a short-lived OAuth token and send
 * each device a message. Invalid tokens are cleaned up automatically.
 */
class FcmService
{
    public function isConfigured(): bool
    {
        return $this->account() !== null;
    }

    public function projectId(): ?string
    {
        return $this->account()['project_id'] ?? null;
    }

    private function account(): ?array
    {
        $json = (string) Setting::get('fcm_service_account', '');
        if ($json === '') {
            return null;
        }
        $data = json_decode($json, true);

        return (isset($data['client_email'], $data['private_key'], $data['project_id'], $data['token_uri']))
            ? $data : null;
    }

    /**
     * @param  iterable<string>  $tokens
     * @return array{sent: int, failed: int, removed: int}
     */
    public function send(iterable $tokens, string $title, string $body, ?string $url = null): array
    {
        $account = $this->account();
        if (! $account) {
            throw new RuntimeException('Firebase service account set nahi hai.');
        }

        $accessToken = $this->accessToken($account);
        $endpoint = "https://fcm.googleapis.com/v1/projects/{$account['project_id']}/messages:send";
        $sent = $failed = $removed = 0;

        foreach ($tokens as $token) {
            $message = [
                'message' => [
                    'token' => $token,
                    'notification' => ['title' => $title, 'body' => $body],
                    'data' => array_filter(['url' => $url, 'title' => $title, 'body' => $body]),
                    'android' => ['priority' => 'high', 'notification' => ['channel_id' => 'dramaearn_general']],
                ],
            ];

            $res = Http::withToken($accessToken)->acceptJson()->post($endpoint, $message);

            if ($res->successful()) {
                $sent++;
            } else {
                $failed++;
                // Token no longer valid → remove it.
                if (in_array($res->status(), [404], true)
                    || str_contains((string) $res->json('error.status'), 'UNREGISTERED')
                    || str_contains((string) $res->json('error.status'), 'INVALID_ARGUMENT')) {
                    DeviceToken::query()->where('token', $token)->delete();
                    $removed++;
                }
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'removed' => $removed];
    }

    /** Mint (and briefly cache) an OAuth access token from the service account. */
    private function accessToken(array $account): string
    {
        return Cache::remember('fcm.access_token.'.md5($account['client_email']), 3000, function () use ($account) {
            $now = time();
            $claims = [
                'iss' => $account['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $account['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ];

            $jwt = $this->sign($claims, $account['private_key']);

            $res = Http::asForm()->post($account['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $res->successful() || ! $res->json('access_token')) {
                throw new RuntimeException('Firebase se access token nahi mila: '.$res->body());
            }

            return $res->json('access_token');
        });
    }

    private function sign(array $claims, string $privateKey): string
    {
        $segments = [
            $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
            $this->b64(json_encode($claims)),
        ];
        $signingInput = implode('.', $segments);

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('JWT sign nahi hua — service account ki private key check karein.');
        }

        return $signingInput.'.'.$this->b64($signature);
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
