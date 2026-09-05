<?php

namespace App\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_id',
        'sender_id',
        'recipient_id',
        'subject',
        'content',
        'attachments',
        'priority',
        'type',
        'is_read',
        'read_at',
        'is_important',
        'is_deleted_by_sender',
        'is_deleted_by_recipient',
    ];

    protected $casts = [
        'attachments' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
        'is_important' => 'boolean',
        'is_deleted_by_sender' => 'boolean',
        'is_deleted_by_recipient' => 'boolean',
    ];

    public function thread()
    {
        return $this->belongsTo(MessageThread::class, 'thread_id');
    }

    /**
     * Get the sender of the message
     *
     * `User::class` here would resolve to this namespace, where no such class
     * exists; the auth config gives the real model without Notifications
     * importing Identity\Models (rule 3).
     */
    public function sender()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'sender_id');
    }

    /**
     * Get the recipient of the message
     */
    public function recipient()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'recipient_id');
    }

    /**
     * Scope for unread messages
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope for read messages
     */
    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    /**
     * Scope for important messages
     */
    public function scopeImportant($query)
    {
        return $query->where('is_important', true);
    }

    /**
     * Scope for messages by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for messages by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for sent messages (not deleted by sender)
     */
    public function scopeSent($query)
    {
        return $query->where('is_deleted_by_sender', false);
    }

    /**
     * Scope for received messages (not deleted by recipient)
     */
    public function scopeReceived($query)
    {
        return $query->where('is_deleted_by_recipient', false);
    }

    /**
     * Mark message as read
     */
    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Mark message as unread
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
        return match ($this->priority) {
            'low' => 'gray',
            'normal' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'blue',
        };
    }

    /**
     * Get the priority icon for UI
     */
    public function getPriorityIconAttribute()
    {
        return match ($this->priority) {
            'low' => 'arrow-down',
            'normal' => 'minus',
            'high' => 'arrow-up',
            'urgent' => 'exclamation-triangle',
            default => 'minus',
        };
    }

    /**
     * Get the time since sent
     */
    public function getTimeSinceSentAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Check if message has attachments
     */
    public function getHasAttachmentsAttribute()
    {
        return ! empty($this->attachments);
    }
}
