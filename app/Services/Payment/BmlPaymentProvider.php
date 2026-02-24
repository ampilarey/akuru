<?php

namespace App\Services\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BmlPaymentProvider implements PaymentProviderInterface
{
    public function initiate(Payment $payment, array $context = []): PaymentInitiationResult
    {
        $baseUrl = rtrim(config('bml.base_url', ''), '/');
        $apiKey  = config('bml.api_key');
        $appId   = config('bml.app_id');

        if (!$baseUrl || !$apiKey) {
            Log::warning('BML payment provider: Missing configuration');
            return new PaymentInitiationResult(false, null, null, 'Payment gateway not configured');
        }

        // BML v2: amount in laari (smallest unit). amount_laar is the canonical integer field.
        // Fall back to amount * 100 for legacy decimal column.
        $amountLaar = isset($payment->amount_laar) && $payment->amount_laar > 0
            ? (int) $payment->amount_laar
            : (int) round((float) $payment->amount * 100);
        // BML requires alphanumeric localId (no hyphens). Strip them.
        $rawLocalId = $payment->local_id ?? $payment->merchant_reference;
        $localId    = preg_replace('/[^A-Za-z0-9]/', '', $rawLocalId);
        // Ensure max 50 chars
        $localId    = substr($localId, 0, 50);

        // Build return URL: context (from controller) > BML_RETURN_URL > APP_URL.
        $baseReturnUrl = null;
        if (! empty($context['return_url']) && is_string($context['return_url'])) {
            $baseReturnUrl = preg_replace('/\?.*/', '', $context['return_url']);
        }
        if (! $baseReturnUrl) {
            $baseReturnUrl = config('bml.return_url')
                ?: rtrim(config('app.url'), '/') . '/payments/bml/return';
        }
        // Pass original ref (with hyphens) so our return controller can look up the payment.
        $returnUrl = $baseReturnUrl . '?ref=' . urlencode($rawLocalId);

        $path    = config('bml.paths.create_transaction', '/v2/transactions');
        // Always use configured BML currency (UAT=USD, production=MVR) regardless of what's stored in the payment record
        $currency = config('bml.default_currency') ?: ($payment->currency ?? 'MVR');

        $payload = [
            'amount'      => $amountLaar,
            'currency'    => $currency,
            'localId'     => $localId,
            'redirectUrl' => $returnUrl,
        ];

        // paymentPortalExperience — required by newer BML Connect API versions
        $portalExp = config('bml.payment_portal_experience', []);
        $payload['paymentPortalExperience'] = [
            'externalWebsiteTermsAccepted' => (bool) ($portalExp['external_website_terms_accepted'] ?? true),
            'externalWebsiteTermsUrl'      => $portalExp['external_website_terms_url']
                                                ?: rtrim(config('app.url'), '/') . '/terms',
        ];

        if ($provider = ($context['provider'] ?? config('bml.provider'))) {
            $payload['provider'] = $provider;
        }

        try {
            $headers = array_merge(
                $this->authHeaders($apiKey, $appId),
                ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            );

            Log::info('BML initiate request', [
                'auth_mode'    => config('bml.auth_mode', 'auto'),
                'url'          => $baseUrl . $path,
                'amount_laar'  => $amountLaar,
                'local_id'     => $localId,
                'redirect_url' => $returnUrl,
                'payload'      => $payload,
            ]);

            $response = Http::withHeaders($headers)
                ->timeout(config('bml.timeout', 30))
                ->post($baseUrl . $path, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $url = $this->extractPaymentUrl($data);
                if ($url) {
                    $payment->update([
                        'local_id'           => $localId,
                        'redirect_url'       => $url,
                        'payment_url'        => $url,
                        'bml_transaction_id' => $this->extractTransactionId($data),
                        'status'             => 'pending',
                    ]);
                    return new PaymentInitiationResult(true, $url, null);
                }
                Log::warning('BML initiate: 200 OK but no payment URL in response', [
                    'response_keys' => array_keys($data ?? []),
                    'body'          => $response->body(),
                ]);
            }

            $body = $response->body();
            $raw = $response->json('message') ?? $body ?? 'Payment initiation failed';
            $code = $response->json('code') ?? '';
            Log::error('BML initiate failed', ['status' => $response->status(), 'code' => $code, 'body' => $body]);

            $error = $this->friendlyPaymentError($response->status(), (string) $raw, (string) $code);
            return new PaymentInitiationResult(false, null, null, $error);
        } catch (\Throwable $e) {
            Log::error('BML initiate exception', ['error' => $e->getMessage()]);
            return new PaymentInitiationResult(false, null, null, 'Payment gateway error');
        }
    }

    /**
     * Extract payment redirect URL from BML response (top-level or nested under "data").
     */
    private function extractPaymentUrl(?array $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }
        $keys = ['url', 'shortUrl', 'paymentUrl', 'redirectUrl', 'payment_url', 'redirect_url'];
        foreach ($keys as $key) {
            if (! empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }
        $nested = $data['data'] ?? null;
        if (is_array($nested)) {
            foreach ($keys as $key) {
                if (! empty($nested[$key]) && is_string($nested[$key])) {
                    return $nested[$key];
                }
            }
        }
        return null;
    }

    /**
     * Extract BML transaction ID from response (top-level or nested under "data").
     */
    private function extractTransactionId(?array $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }
        $id = $data['id'] ?? $data['transactionId'] ?? $data['transaction_id'] ?? null;
        if ($id !== null) {
            return (string) $id;
        }
        $nested = $data['data'] ?? null;
        if (is_array($nested)) {
            $id = $nested['id'] ?? $nested['transactionId'] ?? $nested['transaction_id'] ?? null;
            return $id !== null ? (string) $id : null;
        }
        return null;
    }

    /**
     * Map BML error (401, duplicate, etc.) to a user-friendly message.
     */
    private function friendlyPaymentError(int $status, string $message, string $code): string
    {
        $lower = strtolower($message . ' ' . $code);
        if ($status === 401 || str_contains($lower, 'unauthorized')) {
            return 'Payment service is not available right now. Your registration was saved. Please contact us to complete payment, or try again later.';
        }
        if (str_contains($lower, 'duplicate') || str_contains($lower, 'already exist') || str_contains($lower, 'pp-c-004')) {
            return 'This number or account may already be linked to a payment. Please use a different mobile number, or contact us to complete payment.';
        }
        return $message ?: 'Payment initiation failed. Your registration was saved. Please contact us or try again later.';
    }

    /**
     * Build Authorization headers from BML_AUTH_MODE config.
     *
     * raw          → Authorization: {API_KEY}
     * bearer_jwt   → Authorization: Bearer {API_KEY}
     * bearer_basic → Authorization: Bearer base64(API_KEY:APP_ID)
     * auto (default) → detect: eyJ... = JWT, otherwise bearer_basic
     */
    private function authHeaders(string $apiKey, string $appId): array
    {
        $mode    = config('bml.auth_mode', 'auto');
        $trimmed = trim($apiKey);

        $headerValue = match ($mode) {
            'raw'          => $trimmed,
            'bearer_jwt'   => 'Bearer ' . $trimmed,
            'bearer_basic' => 'Bearer ' . base64_encode($trimmed . ':' . $appId),
            default        => str_starts_with($trimmed, 'eyJ')
                                ? 'Bearer ' . $trimmed
                                : 'Bearer ' . base64_encode($trimmed . ':' . $appId),
        };

        return ['Authorization' => $headerValue];
    }

    /** @deprecated Use authHeaders() */
    private function bmlAuthorizationHeader(string $apiKey, string $appId): string
    {
        return $this->authHeaders($apiKey, $appId)['Authorization'];
    }

    public function verifyCallback(Request $request): PaymentVerificationResult
    {
        // IMPORTANT: Use raw request body for signature verification before any JSON decoding.
        $rawBody = $request->getContent();
        $secret  = config('bml.webhook_secret') ?? config('bml.callback_secret');

        if ($secret) {
            $headerName = config('bml.webhook_signature_header', 'X-BML-Signature');
            $signature  = $request->header($headerName) ?? $request->header('X-BML-Signature');
            if ($signature && ! $this->verifyRawSignature($rawBody, $signature, $secret)) {
                Log::warning('BML webhook: invalid signature');
                return new PaymentVerificationResult(false, null, null, null, [], 'Invalid signature');
            }
        }

        // Only decode JSON after signature is validated
        $payload     = json_decode($rawBody, true) ?? $request->all();
        $merchantRef = $payload['localId'] ?? $payload['reference'] ?? $payload['merchantReference'] ?? $payload['merchant_reference'] ?? null;
        $providerRef = $payload['id'] ?? $payload['transactionId'] ?? $payload['transaction_id'] ?? null;
        $status      = $payload['state'] ?? $payload['status'] ?? $payload['transactionStatus'] ?? null;

        if (! $merchantRef) {
            return new PaymentVerificationResult(false, null, null, null, $payload, 'Missing reference');
        }

        $isConfirmed = in_array(strtolower((string) $status), ['completed', 'success', 'confirmed', 'true'], true);

        return new PaymentVerificationResult(true, $merchantRef, $providerRef, $status, $payload, null, $isConfirmed);
    }

    public function queryStatus(string $merchantReference): ?PaymentVerificationResult
    {
        $baseUrl = rtrim(config('bml.base_url', ''), '/');
        $apiKey  = config('bml.api_key');
        $appId   = config('bml.app_id');

        if (!$baseUrl || !$apiKey) {
            return null;
        }

        try {
            $pathTemplate = config('bml.paths.get_transaction', '/v2/transactions/{reference}');
            $path = str_replace('{reference}', $merchantReference, $pathTemplate);
            Log::info('BML queryStatus request', ['url' => $baseUrl . $path]);
            $response = Http::withHeaders(array_merge(
                $this->authHeaders($apiKey, $appId ?? ''),
                ['Content-Type' => 'application/json'],
            ))->timeout(config('bml.timeout', 30))->get($baseUrl . $path);

            if (!$response->successful()) {
                Log::warning('BML queryStatus: non-success response', [
                    'ref'    => $merchantReference,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $status = $data['state'] ?? $data['status'] ?? $data['transactionStatus'] ?? null;
            Log::info('BML queryStatus response', ['ref' => $merchantReference, 'state' => $status]);
            $providerRef = $data['id'] ?? $data['transactionId'] ?? null;
            $isConfirmed = in_array(strtolower((string) $status), ['completed', 'success', 'confirmed', 'true'], true);

            return new PaymentVerificationResult(true, $merchantReference, $providerRef, $status, $data, null, $isConfirmed);
        } catch (\Throwable $e) {
            Log::warning('BML queryStatus failed', ['ref' => $merchantReference, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verify HMAC signature using raw request body.
     * Uses hash_equals to prevent timing attacks.
     */
    protected function verifyRawSignature(string $rawBody, string $signature, string $secret): bool
    {
        $algo     = config('bml.webhook_hmac_algo', 'sha256');
        $expected = hash_hmac($algo, $rawBody, $secret);
        return hash_equals($expected, $signature);
    }

    /** @deprecated Use verifyRawSignature() with raw body. */
    protected function verifySignature(array $payload, string $signature, string $secret): bool
    {
        return $this->verifyRawSignature(json_encode($payload) ?: '', $signature, $secret);
    }
}
