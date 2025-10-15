<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'content',
        'attachments',
        'submitted_at',
        'status',
        'marks_obtained',
        'teacher_feedback',
        'teacher_feedback_arabic',
        'teacher_feedback_dhivehi',
        'graded_by',
        'graded_at',
        'is_late',
    ];

    protected $casts = [
        'attachments' => 'array',
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'is_late' => 'boolean',
    ];

    /**
     * Get the assignment this submission belongs to
     */
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Get the student who submitted
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the teacher who graded this submission
     */
    public function grader()
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /**
     * Get the percentage score
     */
    public function getPercentageAttribute()
    {
        if (!$this->marks_obtained || !$this->assignment->max_marks) {
            return null;
        }
        
        return round(($this->marks_obtained / $this->assignment->max_marks) * 100, 2);
    }

    /**
     * Get the letter grade based on percentage
     */
    public function getLetterGradeAttribute()
    {
        $percentage = $this->percentage;
        
        if ($percentage === null) return null;
        
        if ($percentage >= 90) return 'A+';
        if ($percentage >= 80) return 'A';
        if ($percentage >= 70) return 'B+';
        if ($percentage >= 60) return 'B';
        if ($percentage >= 50) return 'C+';
        if ($percentage >= 40) return 'C';
        if ($percentage >= 30) return 'D';
        return 'F';
    }

    /**
     * Get the grade color for UI
     */
    public function getGradeColorAttribute()
    {
        $percentage = $this->percentage;
        
        if ($percentage === null) return 'gray';
        
        if ($percentage >= 80) return 'green';
        if ($percentage >= 60) return 'yellow';
        if ($percentage >= 40) return 'orange';
        return 'red';
    }

    /**
     * Check if submission is on time
     */
    public function getIsOnTimeAttribute()
    {
        return !$this->is_late;
    }

    /**
     * Get the time since submission
     */
    public function getTimeSinceSubmissionAttribute()
    {
        return $this->submitted_at->diffForHumans();
    }

    /**
     * Scope for graded submissions
     */
    public function scopeGraded($query)
    {
        return $query->where('status', 'graded');
    }

    /**
     * Scope for pending submissions
     */
    public function scopePending($query)
    {
        return $query->where('status', 'submitted');
    }

    /**
     * Scope for late submissions
     */
    public function scopeLate($query)
    {
        return $query->where('is_late', true);
    }

    /**
     * Scope for on-time submissions
     */
    public function scopeOnTime($query)
    {
        return $query->where('is_late', false);
    }
}