<?php

namespace App\Domains\Offerings\Models;

use App\Domains\Offerings\Enums\AttendanceMode;
use App\Domains\Offerings\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'course_offering_session_id',
        'course_offering_id',
        'enrollment_id',
        'student_id',
        'academic_year_id',
        'status',
        'attendance_mode',
        'marked_by',
        'marked_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'attendance_mode' => AttendanceMode::class,
            'marked_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseOfferingSession::class, 'course_offering_session_id');
    }
}
