<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\SmsReceipt;

class RecordSmsReceiptAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): SmsReceipt
    {
        return SmsReceipt::query()->create([
            'type' => (string) ($payload['type'] ?? 'notification'),
            'reference' => $payload['reference'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'driver' => (string) ($payload['driver'] ?? 'log'),
            'success' => (bool) ($payload['success'] ?? false),
        ]);
    }
}
