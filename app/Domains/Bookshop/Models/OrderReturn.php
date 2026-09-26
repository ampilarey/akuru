<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\ReturnReason;
use App\Domains\Bookshop\Enums\ReturnStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** A customer's request to send part of an order back, and the shop's answer (B3). */
class OrderReturn extends Model
{
    protected $fillable = [
        'order_id', 'order_item_id', 'quantity', 'reason', 'note', 'status', 'refund_amount', 'refunds_delivery', 'restocked',
        'requested_by', 'decided_by', 'decided_at', 'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReturnReason::class,
            'status' => ReturnStatus::class,
            'refund_amount' => 'decimal:2',
            'refunds_delivery' => 'boolean',
            'restocked' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function refund(): HasOne
    {
        return $this->hasOne(OrderRefund::class);
    }
}
