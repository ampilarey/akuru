<?php

namespace App\Domains\Courses\Components\Quran\Models;

use App\Domains\Courses\Components\Quran\Enums\QuranAssignmentStatus;
use App\Domains\Courses\Components\Quran\Enums\QuranAssignmentType;
use Illuminate\Database\Eloquent\Model;

/**
 * SPEC §52.18, engine-keyed. Letter/haraka columns are bare ids here —
 * table-level FKs only, no Arabic component code reference (rule 3).
 */
class QuranHifzAssignment extends Model
{
    protected $fillable = [
        'student_id',
        'teacher_id',
        'course_id',
        'course_offering_id',
        'academic_year_id',
        'surah_id',
        'start_ayah_number',
        'end_ayah_number',
        'expected_letter_id',
        'expected_haraka_id',
        'assignment_type',
        'due_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'assignment_type' => QuranAssignmentType::class,
            'status' => QuranAssignmentStatus::class,
            'due_date' => 'date',
        ];
    }
}
