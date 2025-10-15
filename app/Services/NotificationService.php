<?php

namespace App\Services;

use App\Models\User;
use App\Models\Device;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification;

class NotificationService
{
    protected array $users = [];
    protected string $title = '';
    protected string $body = '';
    protected array $data = [];

    /**
     * Set the users to send notifications to
     */
    public function toUsers($users): self
    {
        if ($users instanceof Collection) {
            $this->users = $users->toArray();
        } elseif (is_array($users)) {
            $this->users = $users;
        } else {
            $this->users = [$users];
        }

        return $this;
    }

    /**
     * Set the notification title
     */
    public function title(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set the notification body
     */
    public function body(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Set additional data
     */
    public function data(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Send the notifications
     */
    public function send(): bool
    {
        try {
            foreach ($this->users as $user) {
                if ($user instanceof User) {
                    $this->sendToUser($user);
                }
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to a specific user
     */
    protected function sendToUser(User $user): void
    {
        // Create database notification
        $user->notifications()->create([
            'id' => \Str::uuid(),
            'type' => 'App\\Notifications\\CustomNotification',
            'data' => [
                'title' => $this->title,
                'body' => $this->body,
                'data' => $this->data,
            ],
            'created_at' => now(),
        ]);

        // Send FCM push notification if user has devices
        $devices = Device::where('user_id', $user->id)
                        ->where('is_active', true)
                        ->get();

        foreach ($devices as $device) {
            $this->sendFcmNotification($device);
        }
    }

    /**
     * Send FCM notification to a device
     */
    protected function sendFcmNotification(Device $device): void
    {
        try {
            // This would require proper FCM setup and credentials
            // For now, we'll just log the notification
            Log::info('FCM Notification would be sent', [
                'device_token' => $device->token,
                'title' => $this->title,
                'body' => $this->body,
                'data' => $this->data,
            ]);

            // Update device last seen
            $device->update(['last_seen_at' => now()]);

        } catch (\Exception $e) {
            Log::error('Failed to send FCM notification: ' . $e->getMessage(), [
                'device_id' => $device->id,
                'user_id' => $device->user_id,
            ]);
        }
    }

    /**
     * Helper methods for common notification types
     */
    public static function assignmentCreated($assignment, $students): bool
    {
        return (new self())
            ->toUsers($students)
            ->title('New Assignment')
            ->body("New assignment: {$assignment->title}")
            ->data([
                'type' => 'assignment_created',
                'assignment_id' => $assignment->id,
                'url' => route('assignments.show', $assignment),
            ])
            ->send();
    }

    public static function quizPublished($quiz, $students): bool
    {
        return (new self())
            ->toUsers($students)
            ->title('New Quiz Available')
            ->body("Quiz available: {$quiz->title}")
            ->data([
                'type' => 'quiz_published',
                'quiz_id' => $quiz->id,
                'url' => route('quizzes.show', $quiz),
            ])
            ->send();
    }

    public static function substitutionAssigned($substitution, $teacher): bool
    {
        return (new self())
            ->toUsers([$teacher])
            ->title('Substitution Assigned')
            ->body("You have been assigned a substitution for {$substitution->date->format('M d')}")
            ->data([
                'type' => 'substitution_assigned',
                'substitution_id' => $substitution->id,
                'url' => route('substitutions.requests.show', $substitution),
            ])
            ->send();
    }

    public static function announcementPublished($announcement, $users): bool
    {
        return (new self())
            ->toUsers($users)
            ->title('New Announcement')
            ->body($announcement->title)
            ->data([
                'type' => 'announcement_published',
                'announcement_id' => $announcement->id,
                'url' => route('announcements.show', $announcement),
            ])
            ->send();
    }
}
