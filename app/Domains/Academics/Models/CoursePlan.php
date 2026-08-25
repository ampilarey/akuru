<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\CoursePlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoursePlan extends Model
{
    protected $fillable = [
        'teacher_id',
        'subject_id',
        'classroom_id',
        'academic_year',
        'academic_year_id',
        'term_id',
        'title',
        'description',
        'objectives',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'objectives' => 'array',
            'status' => CoursePlanStatus::class,
        ];
    }

    public function topics(): HasMany
    {
        return $this->hasMany(PlanTopic::class)->orderBy('order');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'classroom_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}
