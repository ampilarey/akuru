<?php

namespace App\Domains\Pronunciation\Models;

use Illuminate\Database\Eloquent\Model;

class ArabicPronunciationAttempt extends Model
{
    protected $fillable = [
        'student_user_id',
        'course_id',
        'course_offering_id',
        'activity_id',
        'expected_letter_id',
        'expected_haraka_id',
        'audio_media_file_id',
        'mode',
        'duration_seconds',
        'status',
        'ai_prediction_id',
        'teacher_review_required',
    ];

    protected function casts(): array
    {
        return [
            'teacher_review_required' => 'boolean',
        ];
    }
}
