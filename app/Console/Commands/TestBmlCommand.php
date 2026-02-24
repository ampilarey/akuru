<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestBmlCommand extends Command
{
    protected $signature   = 'bml:test {--amount=100 : Amount in MVR (e.g. 100 = MVR 1.00)}';
    protected $description = 'Test BML Connect API configuration and connectivity';

    public function handle(): int
    {
        $this->info('=== BML Connect API Diagnostic ===');
        $this->newLine();

        // 1. Show config
        $baseUrl  = rtrim(config('bml.base_url', ''), '/');
        $apiKey   = config('bml.api_key');
        $appId    = config('bml.app_id');
        $authMode = config('bml.auth_mode', 'auto');
        $currency = config('bml.default_currency', 'MVR');
        $path     = config('bml.paths.create_transaction', '/v2/transactions');
        $returnUrl = config('bml.return_url') ?: rtrim(config('app.url'), '/') . '/payments/bml/return';

        $this->table(['Key', 'Value'], [
            ['BML_BASE_URL',         $baseUrl  ?: '(not set)'],
            ['BML_API_KEY',          $apiKey   ? substr($apiKey, 0, 6) . '...' . substr($apiKey, -4) : '(not set)'],
            ['BML_APP_ID',           $appId    ?: '(not set)'],
            ['BML_AUTH_MODE',        $authMode],
            ['BML_DEFAULT_CURRENCY', $currency],
            ['API endpoint',         $baseUrl . $path],
            ['Return URL',           $returnUrl],
            ['APP_URL',              config('app.url')],
        ]);

        if (! $baseUrl || ! $apiKey) {
            $this->error('Missing BML_BASE_URL or BML_API_KEY — cannot proceed.');
            return 1;
        }

        // 2. Build auth header
        $trimmed = trim($apiKey);
        $headerValue = match ($authMode) {
            'raw'          => $trimmed,
            'bearer_jwt'   => 'Bearer ' . $trimmed,
            'bearer_basic' => 'Bearer ' . base64_encode($trimmed . ':' . $appId),
            default        => str_starts_with($trimmed, 'eyJ')
                                ? 'Bearer ' . $trimmed
                                : 'Bearer ' . base64_encode($trimmed . ':' . $appId),
        };
        $this->line('Authorization header: ' . substr($headerValue, 0, 30) . '...');
        $this->newLine();

        // 3. Send test transaction
        $amountLaar = (int) $this->option('amount');
        $localId    = 'BMLTEST' . strtoupper(substr(md5(microtime()), 0, 12));

        $payload = [
            'amount'      => $amountLaar,
            'currency'    => $currency,
            'localId'     => $localId,
            'redirectUrl' => $returnUrl . '?ref=' . $localId,
            'paymentPortalExperience' => [
                'externalWebsiteTermsAccepted' => true,
                'externalWebsiteTermsUrl'      => rtrim(config('app.url'), '/') . '/terms',
            ],
        ];

        $this->info('Sending test transaction payload:');
        $this->line(json_encode($payload, JSON_PRETTY_PRINT));
        $this->newLine();

        try {
            $response = Http::withHeaders([
                'Authorization' => $headerValue,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->timeout(15)->post($baseUrl . $path, $payload);

            $this->info('HTTP Status: ' . $response->status());
            $this->line('Raw response body:');
            $this->line($response->body());
            $this->newLine();

            $data = $response->json();
            if (is_array($data)) {
                $this->info('Parsed JSON keys: ' . implode(', ', array_keys($data)));
                if (isset($data['data']) && is_array($data['data'])) {
                    $this->line('  -> nested data keys: ' . implode(', ', array_keys($data['data'])));
                }
            }

            // Try to find payment URL
            $keys   = ['url', 'shortUrl', 'paymentUrl', 'redirectUrl', 'payment_url', 'redirect_url'];
            $found  = null;
            foreach ($keys as $k) {
                if (! empty($data[$k])) { $found = $data[$k]; $this->info("Payment URL found at key '{$k}': {$found}"); break; }
            }
            if (! $found && isset($data['data']) && is_array($data['data'])) {
                foreach ($keys as $k) {
                    if (! empty($data['data'][$k])) { $found = $data['data'][$k]; $this->info("Payment URL found at data.{$k}: {$found}"); break; }
                }
            }
            if (! $found) {
                $this->warn('No payment URL found in response. Check the keys above and tell the developer which key holds the URL.');
            }

        } catch (\Throwable $e) {
            $this->error('Exception: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
