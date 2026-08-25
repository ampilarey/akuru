<?php

namespace App\Domains\ExamsGrades\Models;

use Illuminate\Database\Eloquent\Model;

class TermGrade extends Model
{
    protected $fillable = [
        'student_id',
        'class_id',
        'subject_id',
        'term_id',
        'academic_year_id',
        'weighted_percent',
        'grade',
        'grade_point',
        'rank',
        'components',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'weighted_percent' => 'decimal:2',
            'grade_point' => 'decimal:2',
            'rank' => 'integer',
            'components' => 'array',
            'computed_at' => 'datetime',
        ];
    }
}
