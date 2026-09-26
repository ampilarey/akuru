<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stock held for a checkout while its customer pays (plan audit finding
 * 2): thirty minutes, then released by `bookshop:expire-checkouts`.
 */
class StockReservation extends Model
{
    protected $fillable = ['bookshop_checkout_id', 'product_id', 'product_variant_id', 'quantity', 'expires_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}
