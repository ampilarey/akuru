<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** COMMERCE_PARITY_PLAN P6b: one of Akuru's drivers — a person who signs in. */
class DeliveryDriver extends Model
{
    protected $fillable = ['user_id', 'name', 'phone', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
