<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A friend's first Bookstore order that came through a customer's share
 * link (STATUS §5ln). Pending until that order is settled; then paid (both
 * credited through the wallet, the ledger rows noted here) or void.
 */
class Referral extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const VOID = 'void';

    protected $fillable = [
        'referrer_user_id', 'referred_user_id', 'bookshop_checkout_id', 'code', 'status', 'base_amount',
        'referrer_amount', 'friend_amount', 'currency', 'referrer_wallet_transaction_id', 'friend_wallet_transaction_id', 'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'referrer_amount' => 'decimal:2',
            'friend_amount' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(BookshopCheckout::class, 'bookshop_checkout_id');
    }
}
