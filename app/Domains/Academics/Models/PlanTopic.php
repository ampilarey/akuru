<?php

namespace App\Domains\Academics\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanTopic extends Model
{
    protected $fillable = [
        'course_plan_id',
        'order',
        'title',
        'objective',
        'resources',
        'estimated_minutes',
        'materials',
        'assessment_notes',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'materials' => 'array',
            'is_completed' => 'boolean',
            'estimated_minutes' => 'integer',
            'order' => 'integer',
        ];
    }

    public function coursePlan(): BelongsTo
    {
        return $this->belongsTo(CoursePlan::class);
    }
}
