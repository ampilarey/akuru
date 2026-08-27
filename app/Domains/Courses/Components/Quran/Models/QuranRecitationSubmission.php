<?php

namespace App\Domains\Courses\Components\Quran\Models;

use App\Domains\Courses\Components\Quran\Enums\RecitationMode;
use App\Domains\Courses\Components\Quran\Enums\RecitationSubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SPEC §52.19, keyed to engine ids (course_enrollment_id, unified student_id).
 * Engine/other-domain rows are referenced by id only — no relations across the
 * component boundary (rule 3). ai_prediction_id stays unwritten (rule 8).
 */
class QuranRecitationSubmission extends Model
{
    protected $fillable = [
        'course_enrollment_id',
        'quran_hifz_assignment_id',
        'student_id',
        'academic_year_id',
        'surah_id',
        'start_ayah_number',
        'end_ayah_number',
        'audio_media_file_id',
        'mode',
        'duration_seconds',
        'submitted_at',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'mode' => RecitationMode::class,
            'status' => RecitationSubmissionStatus::class,
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function mistakeMarks(): HasMany
    {
        return $this->hasMany(QuranMistakeMark::class, 'quran_recitation_submission_id');
    }
}
