<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
     * Get the status badge color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'submitted' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            default => 'gray'
        };
    }
}