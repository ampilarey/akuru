<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\DeliveryKind;
use App\Domains\Bookshop\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One vendor's share of a checkout (BOOKSHOP_PLAN §4: one order number per
 * vendor, `AK-YYYY-NNNNNN-XXX`). Everything a vendor needs to fulfil it is
 * snapshotted here — items, prices, address, delivery — so a later edit to
 * a product or an address never rewrites what was bought.
 */
class Order extends Model
{
    protected $fillable = [
        'number', 'bookshop_checkout_id', 'vendor_id', 'user_id', 'status', 'delivery_kind', 'delivery_name',
        'delivery_fee', 'delivery_carrier_paid', 'delivery_handling_days', 'address_snapshot', 'subtotal', 'discount',
        'tax', 'total', 'currency', 'tax_shown', 'vendor_tin', 'notes', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivery_kind' => DeliveryKind::class,
            'delivery_fee' => 'decimal:2',
            'delivery_carrier_paid' => 'boolean',
            'address_snapshot' => 'array',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'tax_shown' => 'boolean',
            'paid_at' => 'datetime',
        ];
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(BookshopCheckout::class, 'bookshop_checkout_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }
}
