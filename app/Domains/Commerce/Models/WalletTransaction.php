<?php

namespace App\Domains\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * §35.8, §43.20 — APPEND-ONLY: no updated_at, no update path anywhere in
 * code. A wrong row is corrected by a reversal row.
 */
class WalletTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'type',
        'source_type',
        'source_id',
        'amount',
        'balance_before',
        'balance_after',
        'description',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }
}
