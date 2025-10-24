<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ContactInquiry extends Model
{
    protected $fillable = [
        'inquiry_type_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'priority',
        'admin_notes',
        'response',
        'responded_at',
        'assigned_to',
        'ip_address',
        'user_agent',
        'custom_fields',
        'is_spam',
        'spam_score',
        'meta',
    ];

    protected $casts = [
        'custom_fields' => 'array',
        'is_spam' => 'boolean',
        'responded_at' => 'datetime',
        'meta' => 'array',
    ];

    // Relationships
    public function inquiryType(): BelongsTo
    {
        return $this->belongsTo(InquiryType::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    // Scopes
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByType($query, $typeId)
    {
        return $query->where('inquiry_type_id', $typeId);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    public function scopeSpam($query)
    {
        return $query->where('is_spam', true);
    }

    public function scopeNotSpam($query)
    {
        return $query->where('is_spam', false);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // Accessors
    public function getStatusBadgeColorAttribute()
    {
        return match($this->status) {
            'new' => 'blue',
            'in_progress' => 'yellow',
            'resolved' => 'green',
            'closed' => 'gray',
            default => 'gray',
        };
    }

    public function getPriorityBadgeColorAttribute()
    {
        return match($this->priority) {
            'low' => 'green',
            'medium' => 'blue',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'blue',
        };
    }

    public function getFormattedCreatedAtAttribute()
    {
        return $this->created_at->format('M j, Y g:i A');
    }

    public function getShortCreatedAtAttribute()
    {
        return $this->created_at->diffForHumans();
    }

    public function getIsOverdueAttribute()
    {
        if (!$this->inquiryType || !$this->inquiryType->response_time_hours) {
            return false;
        }
        
        $expectedResponseTime = $this->created_at->addHours($this->inquiryType->response_time_hours);
        return now()->isAfter($expectedResponseTime) && $this->status === 'new';
    }

    public function getDaysSinceCreatedAttribute()
    {
        return $this->created_at->diffInDays(now());
    }

    public function getIsAssignedAttribute()
    {
        return !is_null($this->assigned_to);
    }

    public function getIsRespondedAttribute()
    {
        return !is_null($this->responded_at);
    }

    // Methods
    public function markAsInProgress()
    {
        $this->update(['status' => 'in_progress']);
    }

    public function markAsResolved($response = null)
    {
        $this->update([
            'status' => 'resolved',
            'response' => $response,
            'responded_at' => now(),
        ]);
    }

    public function markAsClosed()
    {
        $this->update(['status' => 'closed']);
    }

    public function assignTo($userId)
    {
        $this->update(['assigned_to' => $userId]);
    }

    public function markAsSpam($score = null)
    {
        $this->update([
            'is_spam' => true,
            'spam_score' => $score ?? $this->spam_score,
        ]);
    }

    public function markAsNotSpam()
    {
        $this->update(['is_spam' => false]);
    }

    public function getInquiryStats()
    {
        return [
            'total' => static::count(),
            'new' => static::new()->count(),
            'in_progress' => static::inProgress()->count(),
            'resolved' => static::resolved()->count(),
            'closed' => static::closed()->count(),
            'spam' => static::spam()->count(),
            'overdue' => static::new()->whereHas('inquiryType', function($q) {
                $q->whereRaw('created_at + INTERVAL response_time_hours HOUR < NOW()');
            })->count(),
        ];
    }

    public function getRecentInquiries($limit = 10)
    {
        return static::notSpam()
                    ->recent()
                    ->with(['inquiryType', 'assignedUser'])
                    ->limit($limit)
                    ->get();
    }

    public function getOverdueInquiries()
    {
        return static::new()
                    ->whereHas('inquiryType', function($q) {
                        $q->whereRaw('created_at + INTERVAL response_time_hours HOUR < NOW()');
                    })
                    ->with(['inquiryType', 'assignedUser'])
                    ->get();
    }
}