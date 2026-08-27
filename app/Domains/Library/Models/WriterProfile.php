<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;

class WriterProfile extends Model
{
    protected $fillable = [
        'user_id',
        'display_name',
        'bio',
        'qualifications',
        'expertise',
        'status',
        'approved_at',
        'approved_by',
        'default_commission',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'default_commission' => 'decimal:2',
        ];
    }
}
