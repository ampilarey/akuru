<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product a signed-in customer saved for later (BOOKSHOP_PLAN §4 "Wishlist"). */
class WishlistItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'product_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
