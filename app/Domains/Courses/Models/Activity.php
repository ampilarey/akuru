<?php

namespace App\Domains\Courses\Models;

use App\Domains\Courses\Enums\ActivityPattern;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'course_module_id',
        'lesson_id',
        'title',
        'description',
        'pattern',
        'activity_type',
        'data',
        'settings',
        'max_score',
        'passing_score',
        'is_required',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'pattern' => ActivityPattern::class,
            'data' => 'array',
            'settings' => 'array',
            'is_required' => 'boolean',
        ];
    }
}
