<?php

namespace App\Domains\ExamsGrades\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentWeightScheme extends Model
{
    protected $fillable = [
        'academic_year_id',
        'class_id',
        'subject_id',
        'weights',
    ];

    protected function casts(): array
    {
        return [
            'weights' => 'array',
        ];
    }
}
