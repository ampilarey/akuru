<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\RefundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money going back for an order (B3): a cancellation or an accepted
 * return. Never edited once done — a second refund is a second row.
 */
class OrderRefund extends Model
{
    protected $fillable = [
        'order_id', 'order_return_id', 'amount', 'currency', 'paid_with', 'status', 'destination', 'reason',
        'payment_refund_id', 'requested_by', 'processed_by', 'processed_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => RefundStatus::class,
            'processed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
