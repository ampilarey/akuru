<?php

namespace App\Domains\ExamsGrades\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamMark extends Model
{
    protected $fillable = [
        'exam_id',
        'student_id',
        'marks',
        'is_absent',
        'is_exempt',
        'remarks',
        'entered_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'marks' => 'decimal:2',
            'is_absent' => 'boolean',
            'is_exempt' => 'boolean',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
