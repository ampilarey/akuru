<?php

namespace App\Domains\Finance\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollPosting extends Model
{
    protected $fillable = [
        'year',
        'month',
        'total_net',
        'staff_count',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'total_net' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }
}
