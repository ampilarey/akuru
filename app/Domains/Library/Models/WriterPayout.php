<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;

class WriterPayout extends Model
{
    protected $fillable = [
        'writer_id',
        'amount',
        'currency',
        // BLOG_PLAN BL6b: `bank`, or `wallet` into `wallet_user_id`'s Akuru wallet.
        'method',
        'wallet_user_id',
        'status',
        'requested_at',
        'decided_by',
        'decided_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
