<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\UserNotification;

class MarkUserNotificationsReadAction
{
    /**
     * Mark one notification read, or all of this user's when no id is given.
     *
     * Scoped by user id in the query rather than checked after loading, so a
     * hand-posted id belonging to somebody else matches nothing instead of
     * being found and then rejected.
     */
    public function execute(int $userId, ?int $notificationId = null): int
    {
        return UserNotification::query()
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->when($notificationId !== null, fn ($query) => $query->whereKey($notificationId))
            ->update(['read_at' => now(), 'status' => 'read']);
    }
}
