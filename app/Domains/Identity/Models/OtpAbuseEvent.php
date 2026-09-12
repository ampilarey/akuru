<?php

namespace App\Domains\Identity\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SPEC §32: a record that an OTP limit was tripped, kept for admin review.
 * Append-only — nothing updates or deletes these except the age prune.
 */
class OtpAbuseEvent extends Model
{
    protected $fillable = [
        'kind', 'purpose', 'channel', 'contact_hash', 'contact_tail',
        'user_id', 'observed', 'threshold', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'observed' => 'integer',
            'threshold' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }
}
