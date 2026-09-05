<?php

namespace App\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MessageThread extends Model
{
    protected $fillable = [
        'subject',
        'created_by',
        'context_type',
        'context_id',
        'reply_policy',
        'last_message_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'thread_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MessageParticipant::class);
    }

    /**
     * The user model is resolved through the auth config rather than imported:
     * Notifications may not reach into Identity\Models (rule 3), and the
     * cross-domain baseline may only shrink.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    /** context_type holds a morph alias, never an FQCN (ADR-005). */
    public function context(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'context_type', 'context_id');
    }

    /**
     * The author can always reply; everyone else is subject to the policy.
     * `author_only` still lets a recipient answer the author privately — it
     * suppresses reply-all, which is the case that turns one broadcast into
     * hundreds of messages.
     */
    public function allowsReplyFrom(int $userId): bool
    {
        if ((int) $this->created_by === $userId) {
            return true;
        }

        return $this->reply_policy !== 'none';
    }

    public function allowsReplyToAll(): bool
    {
        return $this->reply_policy === 'all';
    }
}
