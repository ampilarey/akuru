<?php

namespace App\Domains\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
