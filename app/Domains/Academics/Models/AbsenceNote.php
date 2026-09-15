<?php

namespace App\Domains\Academics\Models;

use App\Domains\People\Models\Student;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsenceNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'created_by',
        'date',
        'period_id',
        'reason',
        'type',
        'absence_type_id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'attachment_path',
        'affects_attendance',
    ];

    protected $casts = [
        'date' => 'date',
        'reviewed_at' => 'datetime',
        'affects_attendance' => 'boolean',
    ];

    /**
     * Get the student
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user who created the note (parent/guardian or student)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who reviewed the note
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the period (if specific period absence)
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    /**
     * Scope for pending notes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope for approved notes
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for rejected notes
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Scope for a specific student
     */
    public function scopeForStudent($query, $studentId)
    {
        return $query->where('student_id', $studentId);
    }

    /**
     * Check if the note is pending review
     */
    public function isPending(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * Check if the note is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if the note is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Does approving this note excuse the register?
     *
     * E10c: the **type** decides, with the per-note boolean as the fallback for
     * notes written before types existed — which is why `affects_attendance` is
     * still written, so the two cannot drift.
     *
     * It lives on the model because two actions now ask the same question from
     * opposite directions: `ApproveAbsenceNoteAction`, when the note arrives
     * after the register, and `RecordClassAttendanceAction`, when the register
     * arrives after the note. Two copies of this rule would be two answers to
     * "is this absence excused" (rule 11), and they would drift the first time
     * the policy changed.
     */
    public function excusesAttendance(): bool
    {
        if ($this->absence_type_id !== null) {
            return (bool) AbsenceType::query()->whereKey($this->absence_type_id)->value('excuses_absence');
        }

        return (bool) $this->affects_attendance;
    }

    /**
     * Get the status badge color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'submitted' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            default => 'gray'
        };
    }
}
