<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\EarningStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a vendor earned on one paid order (BOOKSHOP_PLAN §5 "Money", §8):
 * goods less the discount it funded, plus its delivery fee, less Akuru's
 * commission on the goods. Refunds reverse it in proportion; a payout pays
 * its balance (`net − paid_amount`), so a refund after a payout comes off
 * the next one.
 */
class VendorEarning extends Model
{
    protected $fillable = [
        'vendor_id', 'order_id', 'gross', 'discount', 'discount_funding', 'delivery_fee', 'commission_rate',
        'commission_base', 'commission', 'commission_tax_rate', 'commission_tax', 'net', 'cash_collected', 'refunded', 'paid_amount', 'status',
        'order_paid_at', 'available_at', 'paid_at', 'open_payout_id', 'last_payout_id',
    ];

    protected function casts(): array
    {
        return [
            'gross' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_base' => 'decimal:2',
            'commission' => 'decimal:2',
            'commission_tax_rate' => 'decimal:2',
            'commission_tax' => 'decimal:2',
            'net' => 'decimal:2',
            'cash_collected' => 'decimal:2',
            'refunded' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => EarningStatus::class,
            'order_paid_at' => 'datetime',
            'available_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** What the customer paid for the order: goods less the discount, plus the delivery fee. */
    public function orderTotal(): float
    {
        return round((float) $this->gross - (float) $this->discount + (float) $this->delivery_fee, 2);
    }

    /** How much of the sale has gone back to the customer, 0 to 1. */
    public function refundedFraction(): float
    {
        if ($this->status === EarningStatus::Reversed) {
            return 1.0;
        }
        $total = $this->orderTotal();
        if ($total <= 0) {
            return 0.0;
        }

        return min(1.0, round((float) $this->refunded / $total, 6));
    }

    /** The goods the commission applies to now, after what went back. */
    public function commissionBaseNow(): float
    {
        return round((float) $this->commission_base * (1 - $this->refundedFraction()), 2);
    }

    /** What is still owed (or, after a refund on a paid row, owed back). */
    public function balance(): float
    {
        return round((float) $this->net - (float) $this->paid_amount, 2);
    }

    /** Matured: its return window has passed, so its balance may be paid. */
    public function isMatured(): bool
    {
        return in_array($this->status, [EarningStatus::Available, EarningStatus::Paid], true)
            || ($this->status === EarningStatus::Reversed && (float) $this->paid_amount > 0);
    }
}
