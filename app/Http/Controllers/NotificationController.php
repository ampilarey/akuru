<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\NotificationService;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get user's notifications
     */
    public function index(Request $request)
    {
        $userId = Auth::id();
        $limit = $request->get('limit', 20);
        $unreadOnly = $request->boolean('unread_only', false);

        $notifications = $this->notificationService->getUserNotifications($userId, $limit, $unreadOnly);

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread_count' => UserNotification::getUnreadCount($userId),
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, $id)
    {
        $userId = Auth::id();
        $success = $this->notificationService->markAsRead($id, $userId);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Notification not found',
        ], 404);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $userId = Auth::id();
        $count = $this->notificationService->markAllAsRead($userId);

        return response()->json([
            'success' => true,
            'message' => "Marked {$count} notifications as read",
            'count' => $count,
        ]);
    }

    /**
     * Get notification statistics
     */
    public function stats(Request $request)
    {
        $userId = Auth::id();
        $days = $request->get('days', 30);

        $stats = $this->notificationService->getNotificationStats($userId, $days);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Send test notification
     */
    public function sendTest(Request $request)
    {
        $request->validate([
            'type' => 'required|in:email,sms,push,in_app',
            'title' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        $userId = Auth::id();
        $type = $request->type;
        $title = $request->title;
        $message = $request->message;

        $notification = $this->notificationService->sendToUser(
            $userId,
            $type,
            $title,
            $message,
            ['category' => 'test']
        );

        return response()->json([
            'success' => true,
            'message' => 'Test notification sent successfully',
            'data' => $notification,
        ]);
    }

    /**
     * Get unread count for header
     */
    public function unreadCount()
    {
        $userId = Auth::id();
        $count = UserNotification::getUnreadCount($userId);

        return response()->json([
            'success' => true,
            'count' => $count,
        ]);
    }

    /**
     * Get recent notifications for dropdown
     */
    public function recent()
    {
        $userId = Auth::id();
        $notifications = UserNotification::getRecentForUser($userId, 5);

        return response()->json([
            'success' => true,
            'data' => $notifications,
        ]);
    }
}