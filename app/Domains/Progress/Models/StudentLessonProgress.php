<?php

namespace App\Domains\Progress\Models;

use App\Domains\Progress\Enums\LessonProgressStatus;
use Illuminate\Database\Eloquent\Model;

class StudentLessonProgress extends Model
{
    protected $table = 'student_lesson_progress';

    protected $fillable = [
        'enrollment_id',
        'course_id',
        'course_offering_id',
        'course_module_id',
        'lesson_id',
        'lesson_revision_id',
        'student_id',
        'status',
        'started_at',
        'completed_at',
        'score_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => LessonProgressStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'score_summary' => 'array',
        ];
    }
}
