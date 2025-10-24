<?php

namespace App\Services;

use App\Models\{User, UserNotification, NotificationTemplate};
use App\Services\SmsGatewayService;
use Illuminate\Support\Facades\{Mail, Log, Queue};
use Carbon\Carbon;

class NotificationService
{
    protected $smsService;

    public function __construct(SmsGatewayService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * Send notification to a single user
     */
    public function sendToUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        array $data = [],
        Carbon $scheduledAt = null
    ) {
        $notification = UserNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'category' => $data['category'] ?? 'system',
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
        ]);

        if (!$scheduledAt || $scheduledAt <= now()) {
            $this->processNotification($notification);
        }

        return $notification;
    }

    /**
     * Send notification from template
     */
    public function sendFromTemplate(
        int $userId,
        string $templateName,
        array $variables = [],
        string $type = 'email',
        Carbon $scheduledAt = null
    ) {
        try {
            $notification = UserNotification::createFromTemplate(
                $userId,
                $templateName,
                $variables,
                $type,
                $scheduledAt
            );

            if (!$scheduledAt || $scheduledAt <= now()) {
                $this->processNotification($notification);
            }

            return $notification;
        } catch (\Exception $e) {
            Log::error("Failed to send notification from template: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Send notification to multiple users
     */
    public function sendToUsers(
        array $userIds,
        string $type,
        string $title,
        string $message,
        array $data = [],
        Carbon $scheduledAt = null
    ) {
        $notifications = [];

        foreach ($userIds as $userId) {
            $notifications[] = $this->sendToUser($userId, $type, $title, $message, $data, $scheduledAt);
        }

        return $notifications;
    }

    /**
     * Send notification to users by role
     */
    public function sendToRole(
        string $role,
        string $type,
        string $title,
        string $message,
        array $data = [],
        Carbon $scheduledAt = null
    ) {
        $userIds = User::role($role)->pluck('id')->toArray();
        return $this->sendToUsers($userIds, $type, $title, $message, $data, $scheduledAt);
    }

    /**
     * Process a single notification
     */
    public function processNotification(UserNotification $notification)
    {
        try {
            switch ($notification->type) {
                case 'email':
                    $this->sendEmailNotification($notification);
                    break;
                case 'sms':
                    $this->sendSmsNotification($notification);
                    break;
                case 'push':
                    $this->sendPushNotification($notification);
                    break;
                case 'in_app':
                    $this->sendInAppNotification($notification);
                    break;
                default:
                    throw new \Exception("Unknown notification type: {$notification->type}");
            }

            $notification->markAsSent();
            Log::info("Notification sent successfully", ['notification_id' => $notification->id]);

        } catch (\Exception $e) {
            $notification->markAsFailed($e->getMessage());
            Log::error("Failed to send notification", [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send email notification
     */
    protected function sendEmailNotification(UserNotification $notification)
    {
        $user = $notification->user;
        
        if (!$user->email) {
            throw new \Exception("User has no email address");
        }

        // For now, we'll use a simple mail implementation
        // In production, you'd want to use proper Mailable classes
        Mail::raw($notification->message, function ($mail) use ($user, $notification) {
            $mail->to($user->email)
                 ->subject($notification->title);
        });
    }

    /**
     * Send SMS notification
     */
    protected function sendSmsNotification(UserNotification $notification)
    {
        $user = $notification->user;
        
        if (!$user->phone) {
            throw new \Exception("User has no phone number");
        }

        $result = $this->smsService->sendSms(
            $user->phone,
            $notification->message
        );

        if (!$result['success']) {
            throw new \Exception($result['message'] ?? 'SMS sending failed');
        }
    }

    /**
     * Send push notification
     */
    protected function sendPushNotification(UserNotification $notification)
    {
        // This would integrate with Firebase Cloud Messaging or similar
        // For now, we'll just log it
        Log::info("Push notification would be sent", [
            'user_id' => $notification->user_id,
            'title' => $notification->title,
            'message' => $notification->message
        ]);
    }

    /**
     * Send in-app notification
     */
    protected function sendInAppNotification(UserNotification $notification)
    {
        // In-app notifications are already stored in the database
        // They just need to be marked as sent
        $notification->markAsDelivered();
    }

    /**
     * Process scheduled notifications
     */
    public function processScheduledNotifications()
    {
        $scheduledNotifications = UserNotification::scheduled()->get();

        foreach ($scheduledNotifications as $notification) {
            $this->processNotification($notification);
        }

        return $scheduledNotifications->count();
    }

    /**
     * Retry failed notifications
     */
    public function retryFailedNotifications()
    {
        $failedNotifications = UserNotification::retryable()->get();

        foreach ($failedNotifications as $notification) {
            $this->processNotification($notification);
        }

        return $failedNotifications->count();
    }

    /**
     * Get user's notifications
     */
    public function getUserNotifications(int $userId, int $limit = 20, bool $unreadOnly = false)
    {
        $query = UserNotification::forUser($userId);

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId)
    {
        $notification = UserNotification::where('id', $notificationId)
                                      ->where('user_id', $userId)
                                      ->first();

        if ($notification) {
            $notification->markAsRead();
            return true;
        }

        return false;
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId)
    {
        return UserNotification::forUser($userId)
                              ->unread()
                              ->update(['read_at' => now()]);
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats(int $userId = null, int $days = 30)
    {
        $query = UserNotification::query();

        if ($userId) {
            $query->forUser($userId);
        }

        $query->where('created_at', '>=', now()->subDays($days));

        return [
            'total' => $query->count(),
            'sent' => $query->status('sent')->count(),
            'delivered' => $query->status('delivered')->count(),
            'failed' => $query->status('failed')->count(),
            'unread' => $query->unread()->count(),
            'by_type' => $query->selectRaw('type, count(*) as count')
                              ->groupBy('type')
                              ->pluck('count', 'type'),
            'by_category' => $query->selectRaw('category, count(*) as count')
                                  ->groupBy('category')
                                  ->pluck('count', 'category'),
        ];
    }

    /**
     * Create default notification templates
     */
    public function createDefaultTemplates()
    {
        $templates = [
            // Welcome templates
            [
                'name' => 'welcome_email',
                'type' => 'email',
                'category' => 'system',
                'subject' => 'Welcome to Akuru Institute!',
                'body' => 'Dear {{name}}, welcome to Akuru Institute! We are excited to have you join our community.',
                'variables' => ['name', 'email'],
                'is_system' => true,
            ],
            [
                'name' => 'welcome_sms',
                'type' => 'sms',
                'category' => 'system',
                'subject' => null,
                'body' => 'Welcome to Akuru Institute! Your account has been created successfully.',
                'variables' => ['name'],
                'is_system' => true,
            ],

            // Course enrollment
            [
                'name' => 'course_enrollment',
                'type' => 'email',
                'category' => 'course',
                'subject' => 'Course Enrollment Confirmation',
                'body' => 'Dear {{name}}, you have been successfully enrolled in {{course_name}}. Classes start on {{start_date}}.',
                'variables' => ['name', 'course_name', 'start_date'],
                'is_system' => true,
            ],

            // Assignment due
            [
                'name' => 'assignment_due',
                'type' => 'email',
                'category' => 'assignment',
                'subject' => 'Assignment Due Reminder',
                'body' => 'Dear {{name}}, your assignment "{{assignment_title}}" is due on {{due_date}}. Please submit it on time.',
                'variables' => ['name', 'assignment_title', 'due_date'],
                'is_system' => true,
            ],

            // Event reminder
            [
                'name' => 'event_reminder',
                'type' => 'email',
                'category' => 'event',
                'subject' => 'Upcoming Event Reminder',
                'body' => 'Dear {{name}}, don\'t forget about the event "{{event_title}}" on {{event_date}} at {{event_time}}.',
                'variables' => ['name', 'event_title', 'event_date', 'event_time'],
                'is_system' => true,
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['name' => $template['name'], 'type' => $template['type']],
                $template
            );
        }
    }
}