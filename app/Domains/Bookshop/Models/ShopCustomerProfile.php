<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** COMMERCE_PARITY_PLAN P7c: the office's tags on a Bookstore customer. */
class ShopCustomerProfile extends Model
{
    protected $fillable = ['user_id', 'tags', 'updated_by'];

    protected function casts(): array
    {
        return ['tags' => 'array'];
    }
}
