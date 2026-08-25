<?php

namespace App\Domains\Courses\Models;

use Illuminate\Database\Eloquent\Model;

class CourseLevel extends Model
{
    protected $fillable = [
        'name_en',
        'name_dv',
        'name_ar',
        'slug',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }
}
