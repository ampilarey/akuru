<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A size, colour, edition or grade of a product, with its own SKU, stock
 * and optionally its own price (null = the product's price).
 */
class ProductVariant extends Model
{
    protected $fillable = ['product_id', 'name', 'sku', 'price', 'stock', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
