<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserContact extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'value',
        'is_primary',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function otps(): HasMany
    {
        return $this->hasMany(Otp::class, 'user_contact_id');
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function getChannelAttribute(): string
    {
        return $this->type === 'mobile' ? 'sms' : 'email';
    }
}
