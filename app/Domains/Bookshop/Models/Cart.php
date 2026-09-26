<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A person's basket, or a guest's by session token until they sign in. */
class Cart extends Model
{
    protected $fillable = ['user_id', 'session_token', 'reminded_at'];

    protected function casts(): array
    {
        return ['reminded_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }
}
