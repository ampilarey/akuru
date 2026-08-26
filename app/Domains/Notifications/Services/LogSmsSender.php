<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Log;

class LogSmsSender implements SmsSenderInterface
{
    /**
     * @var list<array{phone: string, message: string, options: array<string, mixed>}>
     */
    public array $sent = [];

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function sendSms(string $phoneNumber, string $message, array $options = []): array
    {
        $this->sent[] = [
            'phone' => $phoneNumber,
            'message' => $message,
            'options' => $options,
        ];

        Log::info('SMS log sender — not delivered', [
            'to' => $phoneNumber,
            'type' => $options['type'] ?? null,
            'reference' => $options['reference'] ?? null,
            'env' => app()->environment(),
        ]);

        return [
            'success' => true,
            'message_id' => 'log_'.uniqid(),
            'status' => 'logged',
            'cost' => 0,
            'driver' => 'log',
        ];
    }
}
