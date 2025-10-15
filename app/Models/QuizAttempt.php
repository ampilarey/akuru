<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'student_id',
        'attempt_number',
        'started_at',
        'finished_at',
        'score',
        'points_earned',
        'total_points',
        'answers',
        'time_spent_seconds',
        'status',
        'feedback',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'score' => 'decimal:2',
        'answers' => 'array',
    ];

    /**
     * Get the quiz
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Get the student
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Check if the attempt is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if the attempt is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Get the time spent in human readable format
     */
    public function getTimeSpentHumanAttribute(): string
    {
        if (!$this->time_spent_seconds) {
            return 'N/A';
        }

        $minutes = floor($this->time_spent_seconds / 60);
        $seconds = $this->time_spent_seconds % 60;

        return "{$minutes}m {$seconds}s";
    }

    /**
     * Get the grade letter based on score
     */
    public function getGradeLetterAttribute(): string
    {
        if (!$this->score) {
            return 'N/A';
        }

        if ($this->score >= 90) return 'A+';
        if ($this->score >= 80) return 'A';
        if ($this->score >= 70) return 'B+';
        if ($this->score >= 60) return 'B';
        if ($this->score >= 50) return 'C+';
        if ($this->score >= 40) return 'C';
        if ($this->score >= 30) return 'D';
        return 'F';
    }

    /**
     * Check if the attempt passed
     */
    public function hasPassed(): bool
    {
        if (!$this->quiz->passing_score || !$this->score) {
            return false;
        }

        return $this->score >= $this->quiz->passing_score;
    }
}