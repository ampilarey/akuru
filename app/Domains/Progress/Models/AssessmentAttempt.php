<?php

namespace App\Domains\Progress\Models;

use App\Domains\Progress\Enums\AssessmentAttemptStatus;
use Illuminate\Database\Eloquent\Model;

class AssessmentAttempt extends Model
{
    protected $fillable = [
        'assessment_id',
        'enrollment_id',
        'student_id',
        'course_id',
        'academic_year_id',
        'attempt_number',
        'status',
        'answers',
        'snapshots',
        'score',
        'max_score',
        'started_at',
        'last_saved_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssessmentAttemptStatus::class,
            'answers' => 'array',
            'snapshots' => 'array',
            'started_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }
}
