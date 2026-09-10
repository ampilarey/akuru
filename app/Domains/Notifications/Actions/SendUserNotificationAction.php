<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\UserNotification;

/**
 * The single place a notification is created, and therefore the single place
 * a category preference can be honoured (E22c).
 *
 * Returns null when the recipient has opted out. Every writer in the app goes
 * through here, so enforcing it once covers all of them — the alternative was
 * six call sites each remembering to check.
 */
class SendUserNotificationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $userId, string $title, string $message, array $data = []): ?UserNotification
    {
        $category = $data['category'] ?? 'hr';

        if (! app(ResolveNotificationPreferencesAction::class)->allows($userId, $category)) {
            return null;
        }

        return UserNotification::query()->create([
            'user_id' => $userId,
            'type' => 'in_app',
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
