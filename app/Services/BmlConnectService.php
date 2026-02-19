<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BmlConnectService
{
    private const STATUS_PAID = ['completed', 'success', 'confirmed', 'true', 'approved', 'paid'];
    private const STATUS_FAILED = ['failed', 'declined', 'rejected', 'false'];
    private const STATUS_CANCELLED = ['cancelled', 'canceled', 'voided'];
    private const STATUS_EXPIRED = ['expired', 'timeout'];
    private const STATUS_REFUNDED = ['refunded'];

    public function __construct(
        protected string $logChannel = 'payments'
    ) {}

    /**
     * Create BML transaction and return payment URL. Amount must be in laari (integer).
     */
    public function createTransaction(Payment $payment, array $options = []): string
    {
        $baseUrl = rtrim(config('bml.base_url', ''), '/');
        $apiKey = config('bml.api_key');
        $appId = config('bml.app_id');
        $path = config('bml.paths.create_transaction', '/v2/transactions');

        if (! $baseUrl || ! $apiKey || ! $appId) {
            $this->log('error', 'BML createTransaction: Missing configuration', ['local_id' => $payment->local_id ?? $payment->merchant_reference ?? null]);
            throw new \RuntimeException('Payment gateway not configured.');
        }

        $localId = $payment->local_id ?? $payment->merchant_reference;
        if (! $localId) {
            $this->log('error', 'BML createTransaction: Payment has no local_id', ['payment_id' => $payment->id]);
            throw new \InvalidArgumentException('Payment must have local_id.');
        }

        $amountLaar = (int) ($payment->amount_laar ?? $this->mvrToLaari((float) $payment->amount));
        if ($amountLaar < 100) {
            $this->log('warning', 'BML createTransaction: Amount too small', ['amount_laar' => $amountLaar, 'local_id' => $localId]);
        }

        $payload = [
            'amount' => $amountLaar,
            'currency' => $payment->currency ?? config('bml.default_currency', 'MVR'),
            'localId' => $localId,
            'redirectUrl' => $options['redirect_url'] ?? $this->returnUrlForPayment($payment),
        ];

        if ($provider = $options['provider'] ?? config('bml.provider')) {
            $payload['provider'] = $provider;
        }
        $webhookUrl = $options['webhook_url'] ?? config('bml.webhook_url');
        if ($webhookUrl) {
            $payload['webhook'] = $webhookUrl;
        }

        $portalExp = config('bml.payment_portal_experience', []);
        $termsUrl = $portalExp['external_website_terms_url'] ?? null;
        if ($termsUrl !== null && $termsUrl !== '') {
            $payload['paymentPortalExperience'] = array_filter([
                'externalWebsiteTermsAccepted' => (bool) ($portalExp['external_website_terms_accepted'] ?? true),
                'externalWebsiteTermsUrl' => $termsUrl,
                'skipProviderSelection' => (bool) ($portalExp['skip_provider_selection'] ?? false),
            ]);
        }

        $url = $baseUrl . $path;
        $this->log('info', 'BML createTransaction request', ['local_id' => $localId, 'amount_laar' => $amountLaar, 'correlation_id' => $localId]);

        try {
            $authHeader = $this->bmlAuthorizationHeader($apiKey, $appId);
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->retry(2, 500)
                ->post($url, $payload);

            $body = $response->json() ?? [];
            $this->log('info', 'BML createTransaction response', ['local_id' => $localId, 'status' => $response->status(), 'body' => $body]);

            if (! $response->successful()) {
                $this->log('error', 'BML createTransaction failed', ['local_id' => $localId, 'status' => $response->status(), 'body' => $response->body()]);
                throw new \RuntimeException($body['message'] ?? $response->body() ?? 'Payment initiation failed.');
            }

            $paymentUrl = $body['url'] ?? $body['shortUrl'] ?? $body['paymentUrl'] ?? $body['redirectUrl'] ?? null;
            $bmlTransactionId = $body['id'] ?? $body['transactionId'] ?? $body['transaction_id'] ?? null;

            if (! $paymentUrl) {
                $this->log('error', 'BML createTransaction: No payment URL in response', ['local_id' => $localId, 'body' => $body]);
                throw new \RuntimeException('Payment gateway did not return a payment URL.');
            }

            $payment->update([
                'payment_url' => $paymentUrl,
                'bml_transaction_id' => $bmlTransactionId,
                'bml_status_raw' => $body,
                'status' => 'pending',
            ]);

            return $paymentUrl;
        } catch (\Throwable $e) {
            $this->log('error', 'BML createTransaction exception', ['local_id' => $localId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * BML UAT may use JWT as API key. If api_key looks like a JWT (eyJ...), send as Bearer; else base64(apiKey:appId).
     */
    private function bmlAuthorizationHeader(string $apiKey, string $appId): string
    {
        $trimmed = trim($apiKey);
        if (str_starts_with($trimmed, 'eyJ')) {
            return 'Bearer ' . $trimmed;
        }
        return 'Bearer ' . base64_encode($apiKey . ':' . $appId);
    }

    /**
     * Fetch transaction status from BML (for reconciliation / fallback).
     */
    public function getTransactionStatus(string $reference): ?array
    {
        $baseUrl = rtrim(config('bml.base_url', ''), '/');
        $apiKey = config('bml.api_key');
        $appId = config('bml.app_id');
        $pathTemplate = config('bml.paths.get_transaction', '/v2/transactions/{reference}');
        $path = str_replace('{reference}', $reference, $pathTemplate);

        if (! $baseUrl || ! $apiKey || ! $appId) {
            return null;
        }

        try {
            $authHeader = $this->bmlAuthorizationHeader($apiKey, $appId);
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json',
            ])
                ->timeout(15)
                ->retry(2, 300)
                ->get($baseUrl . $path);

            if (! $response->successful()) {
                $this->log('warning', 'BML getTransactionStatus failed', ['reference' => $reference, 'status' => $response->status()]);
                return null;
            }

            $data = $response->json();
            $this->log('info', 'BML getTransactionStatus', ['reference' => $reference, 'data' => $data]);
            return $data;
        } catch (\Throwable $e) {
            $this->log('warning', 'BML getTransactionStatus exception', ['reference' => $reference, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Verify webhook signature. Configure header and secret in config. If BML docs differ, adapt here.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = config('bml.webhook_secret');
        if (! $secret) {
            $this->log('warning', 'BML webhook: No webhook_secret configured');
            return false;
        }

        $headerName = config('bml.webhook_signature_header', 'X-BML-Signature');
        $signature = $request->header($headerName) ?? $request->input('signature');
        if (! $signature) {
            $this->log('warning', 'BML webhook: Missing signature header', ['header' => $headerName]);
            return false;
        }

        $payload = $request->getContent();
        if (empty($payload)) {
            $payload = json_encode($request->all());
        }
        $algo = config('bml.webhook_hmac_algo', 'sha256');
        $expected = hash_hmac($algo, $payload, $secret);

        $ok = hash_equals($expected, $signature);
        if (! $ok) {
            $this->log('warning', 'BML webhook: Signature mismatch');
        }
        return $ok;
    }

    /**
     * Check optional IP allowlist for webhook.
     */
    public function isWebhookIpAllowed(Request $request): bool
    {
        $allowlist = config('bml.webhook_ip_allowlist', []);
        if (empty($allowlist)) {
            return true;
        }
        $ip = $request->ip();
        return in_array($ip, $allowlist, true);
    }

    /**
     * Parse webhook payload to normalized structure.
     *
     * @return array{transaction_id: ?string, status: ?string, amount: ?int, currency: ?string, local_id: ?string, paid_at: ?string, raw: array}
     */
    public function parseWebhookPayload(array $payload): array
    {
        $transactionId = $payload['transactionId'] ?? $payload['transaction_id'] ?? $payload['id'] ?? null;
        $status = $payload['status'] ?? $payload['transactionStatus'] ?? $payload['state'] ?? null;
        $amount = isset($payload['amount']) ? (int) $payload['amount'] : null;
        $currency = $payload['currency'] ?? null;
        $localId = $payload['reference'] ?? $payload['merchantReference'] ?? $payload['merchant_reference'] ?? $payload['localId'] ?? null;
        $paidAt = $payload['paidAt'] ?? $payload['paid_at'] ?? $payload['completedAt'] ?? $payload['completed_at'] ?? null;

        return [
            'transaction_id' => $transactionId,
            'status' => $status !== null ? strtolower((string) $status) : null,
            'amount' => $amount,
            'currency' => $currency,
            'local_id' => $localId,
            'paid_at' => $paidAt,
            'raw' => $payload,
        ];
    }

    /**
     * Map BML status string to our payment status.
     */
    /** Map BML state/status to our payments.status enum (initiated, pending, confirmed, failed, cancelled, expired, refunded). */
    public function mapWebhookStatusToPaymentStatus(string $bmlStatus): string
    {
        $s = strtolower($bmlStatus);
        if (in_array($s, self::STATUS_PAID, true)) {
            return 'confirmed';
        }
        if (in_array($s, self::STATUS_FAILED, true)) {
            return 'failed';
        }
        if (in_array($s, self::STATUS_CANCELLED, true)) {
            return 'cancelled';
        }
        if (in_array($s, self::STATUS_REFUNDED, true)) {
            return 'refunded';
        }
        if (in_array($s, self::STATUS_EXPIRED, true)) {
            return 'expired';
        }
        return 'pending';
    }

    public function mvrToLaari(float $mvr): int
    {
        return (int) round($mvr * 100);
    }

    private function returnUrlForPayment(Payment $payment): string
    {
        $base = config('bml.return_url') ?: url('/payments/return/' . $payment->id);
        return $base;
    }

    private function normalizeStatus(string $status): string
    {
        $allowed = ['created', 'pending_redirect', 'pending_webhook', 'paid', 'failed', 'cancelled', 'expired', 'refunded',
            'initiated', 'pending', 'confirmed'];
        return in_array($status, $allowed, true) ? $status : 'created';
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->{$level}($message, $context);
    }
}
