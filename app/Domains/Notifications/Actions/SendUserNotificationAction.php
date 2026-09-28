<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\UserNotification;
use App\Domains\Notifications\Support\PushChannel;
use Illuminate\Support\Facades\Log;

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

        $notification = UserNotification::query()->create([
            'user_id' => $userId,
            'type' => 'in_app',
            'category' => $category,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        $this->push($notification);

        return $notification;
    }

    /**
     * The same notification to the person's phones (SPEC §50, STATUS §5jr),
     * when a push sender is configured. The in-app row is the record and is
     * already written; a phone that cannot be reached never fails it, so the
     * fan-out is best-effort and logged.
     */
    private function push(UserNotification $notification): void
    {
        if (! PushChannel::enabled()) {
            return;
        }

        try {
            $data = $notification->data ?? [];
            app(SendPushNotificationAction::class)->execute((int) $notification->user_id, [
                'title' => $notification->title,
                'body' => $notification->message,
                'data' => [
                    'notification_id' => (string) $notification->id,
                    'category' => (string) $notification->category,
                    'url' => (string) ($data['href'] ?? $data['url'] ?? '/portal/notifications'),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Push fan-out failed', ['notification_id' => $notification->id, 'error' => $e->getMessage()]);
        }
    }
}
