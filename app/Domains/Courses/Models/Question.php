<?php

namespace App\Domains\Courses\Models;

use App\Domains\Courses\Enums\ActivityPattern;
use App\Domains\Courses\Enums\QuestionType;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = [
        'subject_id',
        'category_id',
        'course_id',
        'question_type',
        'pattern',
        'title',
        'question_text',
        'secondary_text',
        'explanation',
        'options',
        'correct_answer',
        'acceptable_answers',
        'normalization_settings',
        'difficulty',
        'skill_tag',
        'attachments',
        'settings',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'pattern' => ActivityPattern::class,
            'options' => 'array',
            'correct_answer' => 'array',
            'acceptable_answers' => 'array',
            'normalization_settings' => 'array',
            'attachments' => 'array',
            'settings' => 'array',
        ];
    }
}
