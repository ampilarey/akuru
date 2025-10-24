<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id',
        'notification_template_id',
        'type',
        'category',
        'title',
        'message',
        'data',
        'status',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'error_message',
        'retry_count',
        'next_retry_at',
    ];

    protected $casts = [
        'data' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class);
    }

    // Scope for specific user
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    // Scope for specific type
    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Scope for specific status
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // Scope for unread notifications
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    // Scope for pending notifications
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    // Scope for scheduled notifications
    public function scopeScheduled($query)
    {
        return $query->where('status', 'pending')
                    ->whereNotNull('scheduled_at')
                    ->where('scheduled_at', '<=', now());
    }

    // Scope for failed notifications that can be retried
    public function scopeRetryable($query)
    {
        return $query->where('status', 'failed')
                    ->where('retry_count', '<', 3)
                    ->where(function ($q) {
                        $q->whereNull('next_retry_at')
                          ->orWhere('next_retry_at', '<=', now());
                    });
    }

    // Mark as read
    public function markAsRead()
    {
        $this->update([
            'read_at' => now(),
        ]);
    }

    // Mark as sent
    public function markAsSent()
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    // Mark as delivered
    public function markAsDelivered()
    {
        $this->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    // Mark as failed
    public function markAsFailed(string $errorMessage = null)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
            'next_retry_at' => $this->retry_count < 3 ? now()->addMinutes(pow(2, $this->retry_count)) : null,
        ]);
    }

    // Check if notification is read
    public function isRead()
    {
        return !is_null($this->read_at);
    }

    // Check if notification is unread
    public function isUnread()
    {
        return is_null($this->read_at);
    }

    // Get notification age
    public function getAgeAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    // Create notification from template
    public static function createFromTemplate(
        int $userId,
        string $templateName,
        array $variables = [],
        string $type = 'email',
        Carbon $scheduledAt = null
    ) {
        $template = NotificationTemplate::getTemplate($templateName, $type);
        
        if (!$template) {
            throw new \Exception("Template '{$templateName}' not found for type '{$type}'");
        }

        // Validate variables
        $validation = $template->validateVariables($variables);
        if ($validation !== true) {
            throw new \Exception("Missing required variables: " . implode(', ', $validation));
        }

        // Process template
        $processed = $template->processTemplate($variables);

        return static::create([
            'user_id' => $userId,
            'notification_template_id' => $template->id,
            'type' => $type,
            'category' => $template->category,
            'title' => $processed['subject'],
            'message' => $processed['body'],
            'data' => $variables,
            'status' => 'pending',
            'scheduled_at' => $scheduledAt,
        ]);
    }

    // Get user's unread count
    public static function getUnreadCount(int $userId)
    {
        return static::forUser($userId)->unread()->count();
    }

    // Get user's recent notifications
    public static function getRecentForUser(int $userId, int $limit = 10)
    {
        return static::forUser($userId)
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get();
    }
}