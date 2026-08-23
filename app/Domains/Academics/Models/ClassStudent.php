<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\ClassStudentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassStudent extends Model
{
    protected $table = 'class_student';

    protected $fillable = [
        'class_id',
        'student_id',
        'academic_year_id',
        'enrolled_at',
        'left_at',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'enrolled_at' => 'date',
            'left_at' => 'date',
            'status' => ClassStudentStatus::class,
        ];
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(config('domain-models.student'));
    }
}
