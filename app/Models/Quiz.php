<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'subject_id',
        'classroom_id',
        'teacher_id',
        'time_limit_min',
        'starts_at',
        'ends_at',
        'max_attempts',
        'passing_score',
        'show_results',
        'shuffle_questions',
        'status',
        'settings',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'passing_score' => 'decimal:2',
        'show_results' => 'boolean',
        'shuffle_questions' => 'boolean',
        'settings' => 'array',
    ];

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
     * Get the teacher who created the quiz
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Get the quiz questions
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('order');
    }

    /**
     * Get the quiz attempts
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Get students who can take this quiz
     */
    public function students(): BelongsToMany
    {
        if ($this->classroom_id) {
            return $this->classroom->students();
        }
        return $this->belongsToMany(Student::class, 'quiz_attempts');
    }

    /**
     * Scope for published quizzes
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Scope for available quizzes (published and within time frame)
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    /**
     * Check if the quiz is currently available
     */
    public function isAvailable(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        $now = now();
        
        if ($this->starts_at && $now < $this->starts_at) {
            return false;
        }

        if ($this->ends_at && $now > $this->ends_at) {
            return false;
        }

        return true;
    }

    /**
     * Get total points for this quiz
     */
    public function getTotalPointsAttribute(): int
    {
        return $this->questions->sum('points');
    }

    /**
     * Get average score for this quiz
     */
    public function getAverageScoreAttribute(): float
    {
        return $this->attempts()->where('status', 'completed')->avg('score') ?? 0;
    }

    /**
     * Get completion rate
     */
    public function getCompletionRateAttribute(): float
    {
        $total = $this->attempts()->count();
        if ($total === 0) return 0;
        
        $completed = $this->attempts()->where('status', 'completed')->count();
        return ($completed / $total) * 100;
    }
}