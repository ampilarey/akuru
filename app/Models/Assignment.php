<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'subject_id',
        'class_id',
        'title',
        'title_arabic',
        'title_dhivehi',
        'description',
        'description_arabic',
        'description_dhivehi',
        'instructions',
        'instructions_arabic',
        'instructions_dhivehi',
        'due_date',
        'due_time',
        'max_marks',
        'type',
        'status',
        'attachments',
        'allow_late_submission',
        'late_penalty_percentage',
        'is_active',
    ];

    protected $casts = [
        'due_date' => 'date',
        'due_time' => 'datetime:H:i',
        'attachments' => 'array',
        'allow_late_submission' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the teacher who created this assignment
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the subject this assignment belongs to
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * Get the class this assignment is for
     */
    public function classRoom()
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    /**
     * Get all submissions for this assignment
     */
    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * Get students who have submitted
     */
    public function submittedStudents()
    {
        return $this->belongsToMany(Student::class, 'assignment_submissions')
            ->withPivot(['submitted_at', 'marks_obtained', 'status'])
            ->withTimestamps();
    }

    /**
     * Get students who haven't submitted yet
     */
    public function pendingStudents()
    {
        $submittedStudentIds = $this->submissions()->pluck('student_id');
        return $this->classRoom->students()->whereNotIn('id', $submittedStudentIds);
    }

    /**
     * Check if assignment is overdue
     */
    public function getIsOverdueAttribute()
    {
        $dueDateTime = Carbon::parse($this->due_date . ' ' . $this->due_time);
        return now()->isAfter($dueDateTime);
    }

    /**
     * Get the due date and time as a single datetime
     */
    public function getDueDateTimeAttribute()
    {
        return Carbon::parse($this->due_date . ' ' . $this->due_time);
    }

    /**
     * Get the time remaining until due date
     */
    public function getTimeRemainingAttribute()
    {
        $dueDateTime = $this->due_date_time;
        if (now()->isAfter($dueDateTime)) {
            return 'Overdue';
        }
        return now()->diffForHumans($dueDateTime, true);
    }

    /**
     * Scope for published assignments
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope for active assignments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for assignments due soon (within 24 hours)
     */
    public function scopeDueSoon($query)
    {
        return $query->where('due_date', '<=', now()->addDay())
            ->where('due_date', '>=', now());
    }

    /**
     * Get the submission rate percentage
     */
    public function getSubmissionRateAttribute()
    {
        $totalStudents = $this->classRoom->students()->count();
        $submittedCount = $this->submissions()->count();
        
        if ($totalStudents === 0) return 0;
        
        return round(($submittedCount / $totalStudents) * 100, 2);
    }
}