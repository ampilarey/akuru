<?php

namespace App\Domains\ExamsGrades\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAward extends Model
{
    protected $fillable = [
        'student_id',
        'award_id',
        'academic_year_id',
        'term_id',
        'awarded_date',
        'notes',
        'certificate_document_id',
    ];

    protected function casts(): array
    {
        return [
            'awarded_date' => 'date',
        ];
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(Award::class);
    }
}
