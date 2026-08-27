<?php

namespace App\Domains\Commerce\Models;

use App\Domains\Commerce\Enums\GiftCardStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * §35.6 — only the SHA-256 hash of the code is stored (§43.19); the plain
 * code leaves IssueGiftCardAction exactly once.
 */
class GiftCard extends Model
{
    protected $fillable = [
        'code_hash',
        'original_amount',
        'balance_amount',
        'currency',
        'purchaser_user_id',
        'recipient_name',
        'recipient_email',
        'recipient_mobile',
        'message',
        'status',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'status' => GiftCardStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class)->orderByDesc('id');
    }
}
