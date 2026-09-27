<?php

namespace App\Domains\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's request for a gift card (§15.3). `pending` until the bank's
 * webhook confirms the payment, then `paid` with the issued card attached
 * and the delivery recorded. Never carries the plain code.
 */
class GiftCardOrder extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        // B4b: a live campaign's bonus on the card, funded by the Institute.
        'bonus_amount',
        'promotion_campaign_id',
        'currency',
        'recipient_name',
        'recipient_email',
        'recipient_mobile',
        'message',
        'status',
        'payment_id',
        'gift_card_id',
        'delivered_via',
        'delivered_to',
        'delivered_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
            'delivered_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function giftCard(): BelongsTo
    {
        return $this->belongsTo(GiftCard::class);
    }
}
