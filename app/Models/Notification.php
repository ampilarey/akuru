<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    protected $table = 'app_notifications';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'is_read',
        'read_at',
        'priority',
        'action_url',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Get the user this notification belongs to
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for unread notifications
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read notifications
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for notifications by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for notifications by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark notification as unread
     */
    public function markAsUnread()
    {
        $this->update([
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    /**
     * Get the priority color for UI
     */
    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            'low' => 'gray',
            'normal' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'blue',
        };
    }

    /**
     * Get the type icon for UI
     */
    public function getTypeIconAttribute()
    {
        return match($this->type) {
            'assignment_due' => 'calendar',
            'grade_posted' => 'star',
            'message_received' => 'envelope',
            'announcement' => 'bullhorn',
            'reminder' => 'clock',
            'system' => 'cog',
            default => 'bell',
        };
    }

    /**
     * Get the time since created
     */
    public function getTimeSinceCreatedAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Create a notification for a user
     */
    public static function createForUser($userId, $type, $title, $message, $data = null, $priority = 'normal', $actionUrl = null)
    {
        return self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => $priority,
            'action_url' => $actionUrl,
        ]);
    }

    /**
     * Create notifications for multiple users
     */
    public static function createForUsers($userIds, $type, $title, $message, $data = null, $priority = 'normal', $actionUrl = null)
    {
        $notifications = [];
        foreach ($userIds as $userId) {
            $notifications[] = [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'priority' => $priority,
                'action_url' => $actionUrl,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        
        return self::insert($notifications);
    }
}