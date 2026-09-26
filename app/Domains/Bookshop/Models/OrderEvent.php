<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only: what happened to an order, when, by whom. Never updated. */
class OrderEvent extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'type', 'actor_user_id', 'note', 'meta', 'created_at'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
