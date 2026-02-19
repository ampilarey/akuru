<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RegistrationFlow extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'contact_id',
        'payment_id',
        'status',
        'payload',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'    => 'array',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $flow) {
            if (empty($flow->uuid)) {
                $flow->uuid = (string) Str::uuid();
            }
            if (empty($flow->expires_at)) {
                $flow->expires_at = now()->addHours(24);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(UserContact::class, 'contact_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at?->isPast() ?? false;
    }

    public function isActive(): bool
    {
        return ! $this->isExpired()
            && in_array($this->status, ['started', 'otp_sent', 'verified', 'selecting_students', 'enrolling', 'payment_pending'], true);
    }

    /** Latest active flow for a user. */
    public static function latestActiveForUser(int $userId): ?self
    {
        return static::where('user_id', $userId)
            ->whereIn('status', ['started', 'otp_sent', 'verified', 'selecting_students', 'enrolling', 'payment_pending'])
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /** Find resumable flow by uuid, optionally scoped to a user. */
    public static function findResumable(string $uuid, ?int $userId = null): ?self
    {
        $query = static::where('uuid', $uuid)
            ->where('expires_at', '>', now());

        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        return $query->first();
    }
}
