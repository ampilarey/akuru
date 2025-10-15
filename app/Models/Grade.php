<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'subject_id',
        'teacher_id',
        'assignment_name',
        'assignment_name_arabic',
        'assignment_name_dhivehi',
        'type',
        'score',
        'max_score',
        'percentage',
        'letter_grade',
        'comments',
        'comments_arabic',
        'comments_dhivehi',
        'date_given',
        'due_date',
        'is_final',
    ];

    protected $casts = [
        'date_given' => 'date',
        'due_date' => 'date',
        'score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'percentage' => 'decimal:2',
        'is_final' => 'boolean',
    ];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    // Helper methods
    public function getGradeAttribute()
    {
        if ($this->percentage >= 90) return 'A+';
        if ($this->percentage >= 85) return 'A';
        if ($this->percentage >= 80) return 'A-';
        if ($this->percentage >= 75) return 'B+';
        if ($this->percentage >= 70) return 'B';
        if ($this->percentage >= 65) return 'B-';
        if ($this->percentage >= 60) return 'C+';
        if ($this->percentage >= 55) return 'C';
        if ($this->percentage >= 50) return 'C-';
        if ($this->percentage >= 45) return 'D+';
        if ($this->percentage >= 40) return 'D';
        return 'F';
    }
}