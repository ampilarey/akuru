<?php

namespace App\Domains\Academics\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A pupil's gate card (E18). The QR on it encodes `AKG:` and the token.
 */
class StudentGateCard extends Model
{
    public const PREFIX = 'AKG:';

    protected $fillable = [
        'student_id',
        'token',
        'issued_by',
        'issued_at',
        'revoked_at',
        'revoked_by',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** The card in a pupil's pocket; a revoked one is history. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    /** What the QR code says, and what a handheld scanner types. */
    public function code(): string
    {
        return self::PREFIX.$this->token;
    }

    /** The token in fours, for a person to read off the card if a scan fails. */
    public function readable(): string
    {
        return implode('-', str_split($this->token, 4));
    }
}
