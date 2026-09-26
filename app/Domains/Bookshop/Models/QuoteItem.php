<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a quote: what was asked for, at what list price, and the shop's price (BOOKSHOP_PLAN B9d). */
class QuoteItem extends Model
{
    protected $fillable = ['quote_request_id', 'product_id', 'product_variant_id', 'title', 'variant_name', 'sku', 'quantity', 'list_price', 'quoted_price'];

    protected function casts(): array
    {
        return ['list_price' => 'decimal:2', 'quoted_price' => 'decimal:2', 'quantity' => 'integer'];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class, 'quote_request_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
