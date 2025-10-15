<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeacherAbsence extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'from_date',
        'to_date',
        'reason',
        'status',
        'note',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the teacher who is absent
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the user who created this absence record
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this absence
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the substitution requests generated from this absence
     */
    public function substitutionRequests(): HasMany
    {
        return $this->hasMany(SubstitutionRequest::class, 'absent_teacher_id', 'teacher_id');
    }

    /**
     * Scope for pending absences
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for approved absences
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Check if the absence is currently active
     */
    public function isActive(): bool
    {
        $now = now()->toDateString();
        return $this->status === 'approved' 
            && $this->from_date <= $now 
            && $this->to_date >= $now;
    }

    /**
     * Get the duration of the absence in days
     */
    public function getDurationAttribute(): int
    {
        return $this->from_date->diffInDays($this->to_date) + 1;
    }
}