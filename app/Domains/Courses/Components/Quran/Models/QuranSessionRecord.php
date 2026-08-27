<?php

namespace App\Domains\Courses\Components\Quran\Models;

use App\Domains\Courses\Components\Quran\Enums\QuranLaneResult;
use App\Domains\Courses\Components\Quran\Enums\QuranRevisionResult;
use App\Domains\Courses\Components\Quran\Enums\QuranSessionOverallStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * F5-P1: the three-lane halaqa session record, engine-keyed. One row per
 * (engine session, enrollment). Attendance lives in attendance_records, not
 * here. Engine/other-domain rows are referenced by id only (rule 3).
 */
class QuranSessionRecord extends Model
{
    protected $fillable = [
        'course_offering_session_id',
        'course_enrollment_id',
        'student_id',
        'academic_year_id',
        'new_from_surah_id',
        'new_from_ayah',
        'new_to_surah_id',
        'new_to_ayah',
        'new_result',
        'new_score',
        'recent_revision_text',
        'recent_revision_result',
        'recent_revision_score',
        'old_revision_text',
        'old_revision_result',
        'old_revision_score',
        'mistake_count',
        'haraka_mistakes',
        'word_mistakes',
        'fluency_mistakes',
        'teacher_note',
        'parent_visible_note',
        'supervisor_note',
        'next_target',
        'requires_parent_attention',
        'requires_supervisor_review',
        'overall_status',
        'reviewed_by',
        'reviewed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'new_result' => QuranLaneResult::class,
            'recent_revision_result' => QuranRevisionResult::class,
            'old_revision_result' => QuranRevisionResult::class,
            'overall_status' => QuranSessionOverallStatus::class,
            'requires_parent_attention' => 'boolean',
            'requires_supervisor_review' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }
}
