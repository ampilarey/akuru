<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\RoomType;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    protected $fillable = [
        'name',
        'name_arabic',
        'name_dhivehi',
        'building',
        'capacity',
        'type',
        'bookable',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => RoomType::class,
            'bookable' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
