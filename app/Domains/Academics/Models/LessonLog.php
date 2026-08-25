<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\People\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'classroom_id',
        'academic_year_id',
        'term_id',
        'timetable_id',
        'status',
        'submitted_at',
        'locked_at',
        'unlocked_until',
        'date',
        'period_id',
        'plan_topic_id',
        'taught_summary',
        'homework',
        'materials',
        'present_count',
        'late_count',
        'absent_count',
        'notes',
        'lesson_quality',
    ];

    protected $casts = [
        'date' => 'date',
        'materials' => 'array',
        'status' => LessonLogStatus::class,
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
        'unlocked_until' => 'datetime',
    ];

    /**
     * Get the teacher
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the subject
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the classroom
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'classroom_id');
    }

    /**
     * Get the period
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    /**
     * Get the plan topic
     */
    public function planTopic(): BelongsTo
    {
        return $this->belongsTo(PlanTopic::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(RegisterUnlock::class);
    }

    /**
     * Scope for today's logs
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    /**
     * Scope for a specific teacher
     */
    public function scopeForTeacher($query, $teacherId)
    {
        return $query->where('teacher_id', $teacherId);
    }

    /**
     * Scope for a specific classroom
     */
    public function scopeForClassroom($query, $classroomId)
    {
        return $query->where('classroom_id', $classroomId);
    }

    /**
     * Get total students count
     */
    public function getTotalStudentsAttribute(): int
    {
        return ($this->present_count ?? 0) + ($this->late_count ?? 0) + ($this->absent_count ?? 0);
    }

    /**
     * Get attendance percentage
     */
    public function getAttendancePercentageAttribute(): float
    {
        $total = $this->total_students;
        if ($total === 0) {
            return 0;
        }

        return (($this->present_count ?? 0) / $total) * 100;
    }
}
