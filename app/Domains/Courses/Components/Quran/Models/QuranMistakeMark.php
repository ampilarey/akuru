<?php

namespace App\Domains\Courses\Components\Quran\Models;

use App\Domains\Courses\Components\Quran\Enums\QuranMistakeSeverity;
use App\Domains\Courses\Components\Quran\Enums\QuranMistakeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SPEC §52.20. Letter/haraka columns reference the Arabic component's tables
 * at the database level only — ids stay plain integers here so the Quran
 * component never references Arabic component code (rule 3 isolation).
 */
class QuranMistakeMark extends Model
{
    protected $fillable = [
        'quran_recitation_submission_id',
        'surah_id',
        'ayah_number',
        'word_position',
        'expected_letter_id',
        'expected_haraka_id',
        'predicted_letter_id',
        'predicted_haraka_id',
        'mistake_type',
        'severity',
        'teacher_id',
        'comment',
        'audio_start_ms',
        'audio_end_ms',
    ];

    protected function casts(): array
    {
        return [
            'mistake_type' => QuranMistakeType::class,
            'severity' => QuranMistakeSeverity::class,
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(QuranRecitationSubmission::class, 'quran_recitation_submission_id');
    }
}
