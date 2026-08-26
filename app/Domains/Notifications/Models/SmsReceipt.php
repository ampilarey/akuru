<?php

namespace App\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

class SmsReceipt extends Model
{
    protected $fillable = [
        'type',
        'reference',
        'phone',
        'driver',
        'success',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
        ];
    }
}
