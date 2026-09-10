<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\UserNotification;
use Illuminate\Support\Collection;

/**
 * A person's notifications, so somebody can finally read them.
 *
 * Five features have been writing `user_notifications` rows for months —
 * unfilled registers, request decisions, substitute assignments, expiring HR
 * documents, the admin daily digest — and **no human could see any of them**.
 * `/notifications` returns JSON with nothing calling it, and
 * `resources/views/notifications/index.blade.php` was rendered by nothing at
 * all. Every one of those notifications went into a table and stopped.
 *
 * `read_at` is the source of truth for unread, not `status` — the model's own
 * `unread()` scope uses it and `status` carries delivery state instead.
 */
class ListUserNotificationsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $userId, int $limit = 50): Collection
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (UserNotification $row): array {
                $data = is_array($row->data) ? $row->data : [];

                return [
                    'id' => (int) $row->id,
                    'category' => (string) $row->category,
                    'title' => (string) $row->title,
                    'message' => (string) $row->message,
                    'is_read' => $row->read_at !== null,
                    'created_at' => $row->created_at?->toIso8601String(),
                    // Notifications that point somewhere are worth far more
                    // than ones that only announce. Writers that supply an
                    // href get a link; the rest render as plain text rather
                    // than a dead one.
                    'href' => is_string($data['href'] ?? null) ? $data['href'] : null,
                ];
            })
            ->values();
    }

    public function unreadCount(int $userId): int
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }
}
