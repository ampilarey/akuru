<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubstitutionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'timetable_entry_id',
        'date',
        'absent_teacher_id',
        'subject_id',
        'classroom_id',
        'period_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Get the timetable entry this substitution is for
     */
    public function timetableEntry(): BelongsTo
    {
        return $this->belongsTo(Timetable::class, 'timetable_entry_id');
    }

    /**
     * Get the absent teacher
     */
    public function absentTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'absent_teacher_id');
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
     * Get the substitution assignment
     */
    public function assignment(): HasOne
    {
        return $this->hasOne(SubstitutionAssignment::class);
    }

    /**
     * Scope for open requests
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope for assigned requests
     */
    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    /**
     * Scope for today's requests
     */
    public function scopeToday($query)
    {
        return $query->whereDate('date', today());
    }

    /**
     * Scope for upcoming requests
     */
    public function scopeUpcoming($query)
    {
        return $query->where('date', '>=', today());
    }

    /**
     * Check if the request can be taken by a teacher
     */
    public function canBeTaken(): bool
    {
        return $this->status === 'open' && $this->date >= today();
    }

    /**
     * Get the substitute teacher if assigned
     */
    public function getSubstituteTeacherAttribute()
    {
        return $this->assignment?->substituteTeacher;
    }
}