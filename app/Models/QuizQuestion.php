<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'order',
        'type',
        'body',
        'explanation',
        'options',
        'answer',
        'points',
        'image_path',
    ];

    protected $casts = [
        'options' => 'array',
        'answer' => 'array',
    ];

    /**
     * Get the quiz this question belongs to
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * Check if the given answer is correct
     */
    public function isCorrectAnswer($studentAnswer): bool
    {
        if ($this->type === 'mcq' || $this->type === 'truefalse') {
            return $studentAnswer === $this->answer;
        } elseif ($this->type === 'short' || $this->type === 'essay') {
            // For text answers, do a simple comparison (could be enhanced with fuzzy matching)
            $correctAnswers = is_array($this->answer) ? $this->answer : [$this->answer];
            $studentAnswerLower = strtolower(trim($studentAnswer));
            
            foreach ($correctAnswers as $correctAnswer) {
                if (strtolower(trim($correctAnswer)) === $studentAnswerLower) {
                    return true;
                }
            }
            return false;
        }
        
        return false;
    }

    /**
     * Get the correct answer as a readable string
     */
    public function getCorrectAnswerTextAttribute(): string
    {
        if ($this->type === 'mcq' && $this->options && $this->answer) {
            $answerIndices = is_array($this->answer) ? $this->answer : [$this->answer];
            $answers = [];
            foreach ($answerIndices as $index) {
                if (isset($this->options[$index])) {
                    $answers[] = $this->options[$index];
                }
            }
            return implode(', ', $answers);
        } elseif ($this->type === 'truefalse') {
            return $this->answer[0] === 0 ? 'True' : 'False';
        } else {
            return is_array($this->answer) ? implode(', ', $this->answer) : $this->answer;
        }
    }
}