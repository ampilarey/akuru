<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassAttendance extends Model
{
    protected $table = 'class_attendance';

    protected $fillable = [
        'student_id',
        'class_id',
        'academic_year_id',
        'term_id',
        'date',
        'period_id',
        'period_key',
        'lesson_log_id',
        'status',
        'minutes_late',
        'source',
        'marked_by',
        'absence_note_id',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => AttendanceStatus::class,
            'source' => AttendanceSource::class,
            'minutes_late' => 'integer',
        ];
    }

    public function lessonLog(): BelongsTo
    {
        return $this->belongsTo(LessonLog::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }
}
