<?php

namespace App\Domains\ExamsGrades\Models;

use App\Domains\ExamsGrades\Enums\GradeScaleType;
use Illuminate\Database\Eloquent\Model;

class GradeScale extends Model
{
    protected $fillable = [
        'name',
        'type',
        'bands',
        'active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'type' => GradeScaleType::class,
            'bands' => 'array',
            'active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
