<?php

namespace App\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

class SmsReceipt extends Model
{
    protected $fillable = [
        'channel',
        'type',
        'reference',
        'phone',
        'body',
        'driver',
        'success',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }
}
