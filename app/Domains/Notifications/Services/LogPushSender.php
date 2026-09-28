<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Contracts\PushSenderInterface;
use Illuminate\Support\Facades\Log;

/**
 * The staging and test sender: the push is written to the log and kept in
 * memory, and counts as sent — the same shape as `LogSmsSender`, so a walk on
 * `test.akuru.edu.mv` can see what a phone would have received.
 */
class LogPushSender implements PushSenderInterface
{
    /**
     * @var list<array{token: string, payload: array<string, mixed>, timestamp: string}>
     */
    public array $sent = [];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendToDevice(string $deviceToken, array $payload): bool
    {
        $record = ['token' => $deviceToken, 'payload' => $payload, 'timestamp' => now()->toIso8601String()];
        $this->sent[] = $record;

        Log::info('Push log sender — not delivered', [
            'channel' => 'push',
            'to' => substr($deviceToken, 0, 12).'…',
            'title' => $payload['title'] ?? null,
            'body' => $payload['body'] ?? null,
            'env' => app()->environment(),
        ]);

        return true;
    }
}
