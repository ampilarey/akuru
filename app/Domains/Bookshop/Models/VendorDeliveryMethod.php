<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\DeliveryKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a vendor gets goods to a customer, at what fee (BOOKSHOP_PLAN §5
 * "Delivery"): free over an amount, a minimum order for the method, and
 * whether the carrier collects the fee on arrival (boats).
 */
class VendorDeliveryMethod extends Model
{
    protected $fillable = [
        'vendor_id', 'kind', 'name', 'name_dv', 'name_ar', 'fee', 'free_over', 'minimum_order',
        'carrier_paid_on_arrival', 'handling_days', 'note', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => DeliveryKind::class,
            'fee' => 'decimal:2',
            'free_over' => 'decimal:2',
            'minimum_order' => 'decimal:2',
            'carrier_paid_on_arrival' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
