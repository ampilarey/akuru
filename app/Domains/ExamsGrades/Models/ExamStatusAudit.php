<?php

namespace App\Domains\ExamsGrades\Models;

use App\Domains\ExamsGrades\Enums\ExamStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamStatusAudit extends Model
{
    protected $fillable = [
        'exam_id',
        'from_status',
        'to_status',
        'actor_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => ExamStatus::class,
            'to_status' => ExamStatus::class,
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
