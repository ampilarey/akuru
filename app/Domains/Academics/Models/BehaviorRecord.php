<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\BehaviorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BehaviorRecord extends Model
{
    protected $fillable = [
        'student_id',
        'academic_year_id',
        'term_id',
        'type',
        'category',
        'description',
        'points',
        'date',
        'recorded_by',
        'parent_visible',
        'requires_followup',
        'followup_notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => BehaviorType::class,
            'points' => 'integer',
            'parent_visible' => 'boolean',
            'requires_followup' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(BehaviorRecordAudit::class);
    }
}
