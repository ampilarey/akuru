<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Contracts\PushSenderInterface;
use App\Domains\Notifications\Models\Device;

/**
 * Push a notification to a person's registered devices.
 *
 * Exists because `NotificationService::sendPushNotification()` wrote a log line
 * and returned, after which the caller marked the notification **sent**. Every
 * push in the system was therefore recorded as delivered while nothing left the
 * building — worse than an unimplemented channel, because the audit trail said
 * otherwise.
 *
 * Sending goes through the domain's own `PushSenderInterface` (rule 4), which is
 * bound to `NullPushSender` until a real provider is configured. Null returns
 * false, so this reports **nothing delivered** and the caller can be honest
 * about it.
 */
class SendPushNotificationAction
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{devices: int, delivered: int}
     */
    public function execute(int $userId, array $payload): array
    {
        $tokens = Device::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereNotNull('token')
            ->pluck('token')
            ->filter(fn ($token): bool => trim((string) $token) !== '')
            ->values();

        if ($tokens->isEmpty()) {
            return ['devices' => 0, 'delivered' => 0];
        }

        $sender = app(PushSenderInterface::class);
        $delivered = 0;

        foreach ($tokens as $token) {
            // One device failing is not the whole notification failing: a
            // person with a dead tablet and a working phone has been reached.
            if ($sender->sendToDevice((string) $token, $payload)) {
                $delivered++;
            }
        }

        return ['devices' => $tokens->count(), 'delivered' => $delivered];
    }
}
