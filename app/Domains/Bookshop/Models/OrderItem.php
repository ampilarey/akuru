<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\TaxClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A line of an order, snapshotted from the product at checkout. */
class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_variant_id', 'title', 'variant_name', 'sku',
        'unit_price', 'quantity', 'line_total', 'tax_class', 'tax_amount',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'tax_class' => TaxClass::class,
            'tax_amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
