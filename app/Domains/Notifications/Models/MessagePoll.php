<?php

namespace App\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessagePoll extends Model
{
    protected $fillable = [
        'message_thread_id',
        'academic_year_id',
        'question',
        'options',
        'closes_at',
    ];

    protected $casts = [
        'options' => 'array',
        'closes_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(MessageThread::class, 'message_thread_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(MessagePollResponse::class);
    }

    /** A poll with no closing date stays open; one with a past date does not. */
    public function isOpen(): bool
    {
        return $this->closes_at === null || $this->closes_at->isFuture();
    }
}
