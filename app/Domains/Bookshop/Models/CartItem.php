<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'product_variant_id', 'quantity', 'quote_item_id', 'saved_at', 'print_job_id'];

    protected function casts(): array
    {
        return ['saved_at' => 'datetime'];
    }

    /** NAME_TAGS_PLAN NT4: the approved print job this line buys — its sheets, at its held price. */
    public function printJob(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class);
    }

    /** B9d: the quoted line this cart line came from, if any. */
    public function quoteItem(): BelongsTo
    {
        return $this->belongsTo(QuoteItem::class);
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
