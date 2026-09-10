<?php

namespace App\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagePollResponse extends Model
{
    protected $fillable = [
        'message_poll_id',
        'user_id',
        'academic_year_id',
        'choice',
        'responded_at',
    ];

    protected $casts = [
        'choice' => 'integer',
        'responded_at' => 'datetime',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(MessagePoll::class, 'message_poll_id');
    }
}
