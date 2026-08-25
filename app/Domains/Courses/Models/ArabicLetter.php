<?php

namespace App\Domains\Courses\Models;

use Illuminate\Database\Eloquent\Model;

class ArabicLetter extends Model
{
    protected $fillable = [
        'key_name',
        'arabic_character',
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
