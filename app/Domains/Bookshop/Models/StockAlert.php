<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** "Out of stock — notify me" (BOOKSHOP_PLAN §4): told once, when the product can be bought again. */
class StockAlert extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'product_id', 'created_at', 'notified_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'notified_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
