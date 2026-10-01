<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;

/** COMMERCE_PARITY_PLAN P7c: an office note on a Bookstore customer, with an optional follow-up date. */
class ShopCustomerNote extends Model
{
    protected $fillable = ['user_id', 'author_id', 'body', 'follow_up_on', 'done_at', 'done_by'];

    protected function casts(): array
    {
        return ['follow_up_on' => 'date', 'done_at' => 'datetime'];
    }
}
