<?php

namespace App\Domains\Commerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only (§43.20).
 */
class GiftCardTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'gift_card_id',
        'user_id',
        'type',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
