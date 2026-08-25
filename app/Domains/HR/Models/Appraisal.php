<?php

namespace App\Domains\HR\Models;

use App\Domains\HR\Enums\AppraisalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appraisal extends Model
{
    protected $fillable = [
        'cycle_id',
        'staff_profile_id',
        'appraiser_id',
        'ratings',
        'strengths',
        'development_areas',
        'goals',
        'status',
        'acknowledged_at',
        'staff_comment',
    ];

    protected function casts(): array
    {
        return [
            'ratings' => 'array',
            'goals' => 'array',
            'status' => AppraisalStatus::class,
            'acknowledged_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AppraisalCycle::class, 'cycle_id');
    }
}
