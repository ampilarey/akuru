<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RecitationPractice extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'surah_id',
        'ayah_from',
        'ayah_to',
        'audio_path',
        'tajweed_notes',
        'tajweed_notes_arabic',
        'tajweed_notes_dhivehi',
        'evaluated_by',
        'evaluated_at',
        'status',
        'accuracy_score',
        'tajweed_score',
        'fluency_score',
        'teacher_feedback',
        'teacher_feedback_arabic',
        'teacher_feedback_dhivehi',
    ];

    protected $casts = [
        'evaluated_at' => 'datetime',
        'accuracy_score' => 'integer',
        'tajweed_score' => 'integer',
        'fluency_score' => 'integer',
    ];

    /**
     * Get the student who practiced
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the surah that was practiced
     */
    public function surah()
    {
        return $this->belongsTo(Surah::class);
    }

    /**
     * Get the teacher who evaluated
     */
    public function evaluator()
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    /**
     * Get the tajweed feedback for this practice
     */
    public function tajweedFeedback()
    {
        return $this->hasMany(TajweedFeedback::class);
    }

    /**
     * Get the overall score (average of all scores)
     */
    public function getOverallScoreAttribute()
    {
        $scores = array_filter([
            $this->accuracy_score,
            $this->tajweed_score,
            $this->fluency_score,
        ]);

        return count($scores) > 0 ? round(array_sum($scores) / count($scores)) : null;
    }

    /**
     * Get the ayah range as a string
     */
    public function getAyahRangeAttribute()
    {
        if ($this->ayah_from === $this->ayah_to) {
            return "Ayah {$this->ayah_from}";
        }
        return "Ayahs {$this->ayah_from}-{$this->ayah_to}";
    }

    /**
     * Scope for pending evaluations
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for evaluated practices
     */
    public function scopeEvaluated($query)
    {
        return $query->where('status', 'evaluated');
    }

    /**
     * Scope for approved practices
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}