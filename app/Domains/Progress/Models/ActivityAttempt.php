<?php

namespace App\Domains\Progress\Models;

use App\Domains\Progress\Enums\ActivityAttemptStatus;
use Illuminate\Database\Eloquent\Model;

class ActivityAttempt extends Model
{
    protected $fillable = [
        'activity_id',
        'enrollment_id',
        'student_id',
        'course_id',
        'academic_year_id',
        'attempt_number',
        'status',
        'answers',
        'score',
        'max_score',
        'started_at',
        'last_saved_at',
        'submitted_at',
        'feedback',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActivityAttemptStatus::class,
            'answers' => 'array',
            'started_at' => 'datetime',
            'last_saved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
