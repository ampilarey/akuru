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

    /** What is in the basket — counted, priced and checked out. Saved-for-later lines are not (§5lf). */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->whereNull('saved_at')->orderBy('id');
    }

    /** §5lf: lines the customer set aside, newest first. */
    public function savedItems(): HasMany
    {
        return $this->hasMany(CartItem::class)->whereNotNull('saved_at')->orderByDesc('saved_at')->orderByDesc('id');
    }

    /** Every line, in the basket or set aside — for merging a guest's basket into their own. */
    public function allItems(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }
}
