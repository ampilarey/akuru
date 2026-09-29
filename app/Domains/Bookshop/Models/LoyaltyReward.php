<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reward paid into a customer's wallet for one order (STATUS §5lm).
 * Written once, with the wallet credit it made; never edited or deleted.
 */
class LoyaltyReward extends Model
{
    protected $fillable = ['user_id', 'order_id', 'base_amount', 'percent', 'amount', 'currency', 'wallet_transaction_id'];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'percent' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
