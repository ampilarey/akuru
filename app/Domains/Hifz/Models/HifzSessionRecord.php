<?php

namespace App\Domains\Hifz\Models;

use App\Domains\Hifz\Models\HifzMistake;
use App\Domains\Hifz\Models\HifzProgram;
use App\Domains\Hifz\Models\HifzSession;
use App\Domains\Hifz\Models\Surah;

use App\Domains\People\Models\Teacher;

use App\Domains\People\Models\Student;

use App\Enums\Hifz\HifzAttendanceStatus;
use App\Enums\Hifz\HifzLaneResult;
use App\Enums\Hifz\HifzOverallStatus;
use App\Enums\Hifz\HifzRevisionResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HifzSessionRecord extends Model
{
    protected $fillable = [
        'hifz_session_id', 'hifz_program_id', 'student_id', 'teacher_id', 'supervisor_id',
        'attendance_status', 'new_from_surah_id', 'new_from_ayah', 'new_to_surah_id', 'new_to_ayah',
        'new_result', 'new_score', 'recent_revision_text', 'recent_revision_result', 'recent_revision_score',
        'old_revision_text', 'old_revision_result', 'old_revision_score',
        'mistake_count', 'haraka_mistakes', 'word_mistakes', 'fluency_mistakes',
        'teacher_note', 'parent_visible_note', 'supervisor_note', 'next_target',
        'requires_parent_attention', 'requires_supervisor_review', 'overall_status',
        'reviewed_by', 'reviewed_at', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'attendance_status' => HifzAttendanceStatus::class,
        'new_result' => HifzLaneResult::class,
        'recent_revision_result' => HifzRevisionResult::class,
        'old_revision_result' => HifzRevisionResult::class,
        'overall_status' => HifzOverallStatus::class,
        'requires_parent_attention' => 'boolean',
        'requires_supervisor_review' => 'boolean',
        'reviewed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(HifzSession::class, 'hifz_session_id');
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(HifzProgram::class, 'hifz_program_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function mistakes(): HasMany
    {
        return $this->hasMany(HifzMistake::class);
    }

    public function newFromSurah(): BelongsTo
    {
        return $this->belongsTo(Surah::class, 'new_from_surah_id');
    }

    public function newToSurah(): BelongsTo
    {
        return $this->belongsTo(Surah::class, 'new_to_surah_id');
    }
}
