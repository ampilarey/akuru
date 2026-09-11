<?php

namespace App\Domains\Academics\Models;

use Illuminate\Database\Eloquent\Model;

class PickupWindow extends Model
{
    protected $fillable = ['date', 'opened_by', 'opened_at', 'closed_at'];

    protected $casts = [
        'date' => 'date',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];
}
