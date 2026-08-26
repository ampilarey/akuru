<?php

namespace App\Domains\Courses\Models;

use App\Domains\Courses\Enums\AssessmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assessment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'course_id',
        'classroom_id',
        'academic_year_id',
        'term_id',
        'course_module_id',
        'lesson_id',
        'title',
        'description',
        'assessment_type',
        'status',
        'time_limit_minutes',
        'passing_score',
        'max_score',
        'retake_limit',
        'randomize_questions',
        'show_results',
        'show_correct_answers',
        'requires_teacher_marking',
        'settings',
        'created_by',
        'legacy_quiz_id',
        'legacy_assignment_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssessmentStatus::class,
            'randomize_questions' => 'boolean',
            'show_results' => 'boolean',
            'show_correct_answers' => 'boolean',
            'requires_teacher_marking' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AssessmentQuestion::class)->orderBy('position');
    }
}
