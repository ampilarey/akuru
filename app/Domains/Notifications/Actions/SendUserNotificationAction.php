<?php

namespace App\Domains\Notifications\Actions;

use App\Domains\Notifications\Models\UserNotification;

class SendUserNotificationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $userId, string $title, string $message, array $data = []): UserNotification
    {
        return UserNotification::query()->create([
            'user_id' => $userId,
            'type' => 'in_app',
            'category' => $data['category'] ?? 'hr',
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }
}
