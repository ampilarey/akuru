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
        $apiKey = config('bml.api_key');
        $appId = config('bml.app_id');
        $returnUrl = config('bml.return_url') ?? ($context['return_url'] ?? url('/payments/bml/return'));

        if (!$baseUrl || !$apiKey || !$appId) {
            Log::warning('BML payment provider: Missing configuration');
            return new PaymentInitiationResult(false, null, null, 'Payment gateway not configured');
        }

        // BML v2: amount in smallest unit (laari for MVR: 10.00 MVR = 1000)
        $amountLaar = (int) round((float) ($payment->amount_laar ?? $payment->amount * 100));
        $localId = $payment->local_id ?? $payment->merchant_reference;

        $path = config('bml.paths.create_transaction', '/v2/transactions');
        $payload = [
            'amount' => $amountLaar,
            'currency' => $payment->currency ?? 'MVR',
            'localId' => $localId,
            'redirectUrl' => $returnUrl,
        ];

        if ($provider = ($context['provider'] ?? config('bml.provider'))) {
            $payload['provider'] = $provider;
        }

        try {
            $authHeader = $this->bmlAuthorizationHeader($apiKey, $appId);
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . $path, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $url = $data['url'] ?? $data['shortUrl'] ?? $data['paymentUrl'] ?? $data['redirectUrl'] ?? null;
                if ($url) {
                    $payment->update([
                        'redirect_url' => $url,
                        'payment_url' => $url,
                        'bml_transaction_id' => $data['id'] ?? $data['transactionId'] ?? null,
                        'status' => 'pending',
                    ]);
                    return new PaymentInitiationResult(true, $url, null);
                }
            }

            $raw = $response->json('message') ?? $response->body() ?? 'Payment initiation failed';
            Log::error('BML initiate failed', ['status' => $response->status(), 'body' => $response->body()]);
            // Don't show BML's "Unauthorized" (401) to users; use a friendly message
            $error = ($response->status() === 401 || stripos((string) $raw, 'unauthorized') !== false)
                ? 'Payment service is not available right now. Your registration was saved. Please contact us to complete payment, or try again later.'
                : $raw;
            return new PaymentInitiationResult(false, null, null, $error);
        } catch (\Throwable $e) {
            Log::error('BML initiate exception', ['error' => $e->getMessage()]);
            return new PaymentInitiationResult(false, null, null, 'Payment gateway error');
        }
    }

    /**
     * BML UAT may use JWT as API key; production may use base64(app_id:api_key).
     * If api_key looks like a JWT (eyJ...), send as Bearer token; otherwise use legacy format.
     */
    private function bmlAuthorizationHeader(string $apiKey, string $appId): string
    {
        $trimmed = trim($apiKey);
        if (str_starts_with($trimmed, 'eyJ')) {
            return 'Bearer ' . $trimmed;
        }
        return 'Bearer ' . base64_encode($apiKey . ':' . $appId);
    }

    public function verifyCallback(Request $request): PaymentVerificationResult
    {
        $payload = $request->all();
        $secret = config('bml.webhook_secret') ?? config('bml.callback_secret');

        if ($secret) {
            $signature = $request->header('X-BML-Signature') ?? $request->input('signature');
            if ($signature && !$this->verifySignature($payload, $signature, $secret)) {
                return new PaymentVerificationResult(false, null, null, null, $payload, 'Invalid signature');
            }
        }

        $merchantRef = $payload['localId'] ?? $payload['reference'] ?? $payload['merchantReference'] ?? $payload['merchant_reference'] ?? null;
        $providerRef = $payload['id'] ?? $payload['transactionId'] ?? $payload['transaction_id'] ?? null;
        $status = $payload['state'] ?? $payload['status'] ?? $payload['transactionStatus'] ?? null;

        if (!$merchantRef) {
            return new PaymentVerificationResult(false, null, null, null, $payload, 'Missing reference');
        }

        $isConfirmed = in_array(strtolower((string) $status), ['completed', 'success', 'confirmed', 'true'], true);

        return new PaymentVerificationResult(true, $merchantRef, $providerRef, $status, $payload, null, $isConfirmed);
    }

    public function queryStatus(string $merchantReference): ?PaymentVerificationResult
    {
        $baseUrl = rtrim(config('bml.base_url', ''), '/');
        $apiKey = config('bml.api_key');
        $appId = config('bml.app_id');

        if (!$baseUrl || !$apiKey || !$appId) {
            return null;
        }

        try {
            $pathTemplate = config('bml.paths.get_transaction', '/v2/transactions/{reference}');
            $path = str_replace('{reference}', $merchantReference, $pathTemplate);
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . base64_encode($apiKey . ':' . $appId),
                'Content-Type' => 'application/json',
            ])->get($baseUrl . $path);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $status = $data['state'] ?? $data['status'] ?? $data['transactionStatus'] ?? null;
            $providerRef = $data['id'] ?? $data['transactionId'] ?? null;
            $isConfirmed = in_array(strtolower((string) $status), ['completed', 'success', 'confirmed', 'true'], true);

            return new PaymentVerificationResult(true, $merchantReference, $providerRef, $status, $data, null, $isConfirmed);
        } catch (\Throwable $e) {
            Log::warning('BML queryStatus failed', ['ref' => $merchantReference, 'error' => $e->getMessage()]);
            return null;
        }
    }

    protected function verifySignature(array $payload, string $signature, string $secret): bool
    {
        $data = json_encode($payload);
        $expected = hash_hmac('sha256', $data, $secret);

        return hash_equals($expected, $signature);
    }
}
