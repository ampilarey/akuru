<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** COMMERCE_PARITY_PLAN P7b: a customer who asked for the Bookstore's offers by SMS — or has since said stop. */
class ShopSmsOptin extends Model
{
    protected $fillable = ['user_id', 'phone', 'token', 'source', 'opted_in_at', 'opted_out_at'];

    protected function casts(): array
    {
        return ['opted_in_at' => 'datetime', 'opted_out_at' => 'datetime'];
    }
}
