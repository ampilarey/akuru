<?php

namespace App\Domains\ExamsGrades\Models;

use App\Domains\ExamsGrades\Enums\ExamStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    protected $fillable = [
        'academic_year_id',
        'term_id',
        'class_id',
        'subject_id',
        'exam_type_id',
        'name',
        'exam_date',
        'start_time',
        'end_time',
        'room_id',
        'max_marks',
        'weight_override',
        'instructions',
        'status',
        'created_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'start_time' => 'datetime:H:i:s',
            'end_time' => 'datetime:H:i:s',
            'max_marks' => 'decimal:2',
            'weight_override' => 'integer',
            'status' => ExamStatus::class,
            'published_at' => 'datetime',
        ];
    }

    public function examType(): BelongsTo
    {
        return $this->belongsTo(ExamType::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(ExamStatusAudit::class);
    }
}
