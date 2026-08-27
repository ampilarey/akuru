<?php

namespace App\Domains\Courses\Components\Arabic\Models;

use Illuminate\Database\Eloquent\Model;

class ArabicHarakah extends Model
{
    protected $table = 'arabic_harakas';

    protected $fillable = [
        'key_name',
        'symbol',
        'display_name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
