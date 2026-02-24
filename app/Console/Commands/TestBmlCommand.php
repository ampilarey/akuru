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

        $headers = [
            'Authorization' => $headerValue,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];

        $this->info('--- Attempt 1: configured currency (' . $currency . ') with paymentPortalExperience ---');
        $result = $this->postAndReport($baseUrl . $path, $headers, $payload);

        if (! $result && $currency === 'MVR') {
            $this->newLine();
            $this->warn('Configured currency MVR failed. UAT environment usually requires USD. Trying USD...');
            $payload2 = array_merge($payload, ['currency' => 'USD', 'localId' => 'BMLTEST' . strtoupper(substr(md5(microtime(true)), 0, 12))]);
            $this->info('--- Attempt 2: USD currency ---');
            $result = $this->postAndReport($baseUrl . $path, $headers, $payload2);
        }

        if (! $result) {
            $this->newLine();
            $this->warn('Trying without paymentPortalExperience...');
            $payload3 = $payload;
            unset($payload3['paymentPortalExperience']);
            $payload3['currency'] = 'USD';
            $payload3['localId']  = 'BMLTEST' . strtoupper(substr(md5(microtime(true) . 'x'), 0, 12));
            $this->info('--- Attempt 3: USD, no paymentPortalExperience ---');
            $result = $this->postAndReport($baseUrl . $path, $headers, $payload3);
        }

        if ($result) {
            $this->newLine();
            $this->info('SUCCESS — open this URL in a browser to verify the BML payment page loads:');
            $this->line($result);
            $this->newLine();
            $this->warn('ACTION REQUIRED: If attempt 1 failed but attempt 2/3 succeeded, set BML_DEFAULT_CURRENCY=USD in .env on the server.');
        } else {
            $this->newLine();
            $this->error('All attempts failed. Share the output above with the developer.');
        }

        return 0;
    }

    private function postAndReport(string $url, array $headers, array $payload): ?string
    {
        try {
            $response = Http::withHeaders($headers)->timeout(15)->post($url, $payload);

            $this->line('HTTP Status: ' . $response->status());
            $this->line('Response: ' . $response->body());

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $keys = ['url', 'shortUrl', 'paymentUrl', 'redirectUrl', 'payment_url', 'redirect_url'];
            foreach ($keys as $k) {
                if (! empty($data[$k]) && is_string($data[$k])) {
                    $this->info("  -> Payment URL at '{$k}': " . $data[$k]);
                    return $data[$k];
                }
            }
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($keys as $k) {
                    if (! empty($data['data'][$k]) && is_string($data['data'][$k])) {
                        $this->info("  -> Payment URL at 'data.{$k}': " . $data['data'][$k]);
                        return $data['data'][$k];
                    }
                }
                $this->warn('  -> 200 OK but no URL key found. data keys: ' . implode(', ', array_keys($data['data'])));
            } else {
                $this->warn('  -> 200 OK but no URL key found. keys: ' . implode(', ', array_keys($data ?? [])));
            }
            return null;
        } catch (\Throwable $e) {
            $this->error('Exception: ' . $e->getMessage());
            return null;
        }
    }
}
