<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product photo: public media, first by `sort_order` is the card image. */
class ProductImage extends Model
{
    protected $fillable = ['product_id', 'media_file_id', 'alt_text', 'sort_order'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
