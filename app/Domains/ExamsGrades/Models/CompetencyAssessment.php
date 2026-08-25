<?php

namespace App\Domains\ExamsGrades\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetencyAssessment extends Model
{
    protected $fillable = [
        'student_id',
        'competency_id',
        'term_id',
        'level',
        'assessed_by',
        'notes',
    ];

    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}
