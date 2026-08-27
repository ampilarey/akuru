<?php

namespace App\Domains\Courses\Components\Quran\Models;

use App\Domains\Courses\Components\Quran\Enums\MemorizationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * SPEC §52.22 — one row per student per memorised range, engine-keyed.
 * Distinct from the legacy quran_progress table (frozen Hifz Blade app),
 * which F5 archives.
 */
class QuranMemorizationProgress extends Model
{
    protected $table = 'quran_memorization_progress';

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'surah_id',
        'start_ayah_number',
        'end_ayah_number',
        'status',
        'last_reviewed_at',
        'strength_score',
        'mistake_count',
        'teacher_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => MemorizationStatus::class,
            'last_reviewed_at' => 'datetime',
        ];
    }
}
