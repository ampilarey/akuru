<?php

namespace App\Domains\Courses\Components\Quran\Models;

use App\Domains\Courses\Components\Quran\Enums\RevisionScheduleStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * SPEC §52.21. Rule 10: carries academic_year_id.
 */
class QuranRevisionSchedule extends Model
{
    protected $fillable = [
        'student_id',
        'teacher_id',
        'academic_year_id',
        'surah_id',
        'start_ayah_number',
        'end_ayah_number',
        'scheduled_date',
        'frequency',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'status' => RevisionScheduleStatus::class,
        ];
    }
}
