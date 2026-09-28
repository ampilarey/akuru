<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Contracts\InvalidDeviceTokenException;
use App\Domains\Notifications\Contracts\PushSenderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging, HTTP v1 (SPEC §50, STATUS §5jr). No SDK: a
 * service-account key signs a short-lived JWT, Google exchanges it for an
 * access token (cached for 50 minutes), and each push is one POST. The
 * wrapper layer under rule 4 — nothing outside `Services/` sees Firebase.
 *
 * FCM answers UNREGISTERED (the app was uninstalled) or INVALID_ARGUMENT (a
 * malformed token) for a dead token; those raise `InvalidDeviceTokenException`
 * so the caller can retire the device instead of retrying it forever. Any
 * other failure is logged and reported as not delivered.
 */
class FcmPushSender implements PushSenderInterface
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    public function __construct(private string $projectId, private string $credentialsPath, private int $timeout = 5) {}

    /**
     * @param  array<string, mixed>  $payload  title, body, data (flat strings)
     */
    public function sendToDevice(string $deviceToken, array $payload): bool
    {
        $response = Http::withToken($this->accessToken())
            ->timeout($this->timeout)
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send", [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => (string) ($payload['title'] ?? ''),
                        'body' => (string) ($payload['body'] ?? ''),
                    ],
                    // FCM data values must be strings; nested arrays are flattened to JSON.
                    'data' => collect($payload['data'] ?? [])
                        ->map(fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value))
                        ->all(),
                ],
            ]);

        if ($response->successful()) {
            return true;
        }

        $code = (string) $response->json('error.details.0.errorCode', $response->json('error.status', ''));
        if (in_array($code, ['UNREGISTERED', 'INVALID_ARGUMENT'], true) || $response->status() === 404) {
            throw new InvalidDeviceTokenException("FCM refused the token: {$code}");
        }

        Log::warning('FCM push not delivered', ['status' => $response->status(), 'error' => $response->json('error.message')]);

        return false;
    }

    /** A Google OAuth2 access token from the service-account key, cached just short of its hour. */
    private function accessToken(): string
    {
        return Cache::remember('push.fcm.access_token', now()->addMinutes(50), function (): string {
            $key = json_decode((string) file_get_contents($this->credentialsPath), true);
            if (! is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
                throw new RuntimeException('The FCM service-account key is unreadable or incomplete.');
            }

            $now = time();
            $jwt = $this->jwt(
                ['alg' => 'RS256', 'typ' => 'JWT'],
                ['iss' => $key['client_email'], 'scope' => self::SCOPE, 'aud' => self::TOKEN_URL, 'iat' => $now, 'exp' => $now + 3600],
                (string) $key['private_key'],
            );

            $response = Http::asForm()->timeout($this->timeout)->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);
            $token = (string) $response->json('access_token', '');
            if ($token === '') {
                throw new RuntimeException('Google did not issue an access token for the FCM key: '.$response->status());
            }

            return $token;
        });
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<string, mixed>  $claims
     */
    private function jwt(array $header, array $claims, string $privateKey): string
    {
        $segments = [$this->base64url((string) json_encode($header)), $this->base64url((string) json_encode($claims))];
        $signature = '';
        if (! openssl_sign(implode('.', $segments), $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the FCM assertion with the service-account key.');
        }
        $segments[] = $this->base64url($signature);

        return implode('.', $segments);
    }

    private function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
