<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class Otp extends Model
{
    protected $table = 'user_contact_otps';

    protected $fillable = [
        'user_contact_id',
        'purpose',
        'channel',
        'code_hash',
        'expires_at',
        'used_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function userContact(): BelongsTo
    {
        return $this->belongsTo(UserContact::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function verify(string $code): bool
    {
        if ($this->isUsed() || $this->isExpired()) {
            return false;
        }
        if (!Hash::check($code, $this->code_hash)) {
            $this->increment('attempts');
            return false;
        }
        $this->update(['used_at' => now()]);
        return true;
    }

    public static function createForContact(UserContact $contact, string $purpose, string $code): self
    {
        return static::create([
            'user_contact_id' => $contact->id,
            'purpose' => $purpose,
            'channel' => $contact->channel,
            'code_hash' => Hash::make($code),
            'expires_at' => $purpose === 'verify_contact'
                ? now()->addMinutes(5)
                : now()->addMinutes(15),
        ]);
    }
}
