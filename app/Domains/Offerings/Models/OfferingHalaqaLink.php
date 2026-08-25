<?php

namespace App\Domains\Offerings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferingHalaqaLink extends Model
{
    protected $fillable = [
        'course_offering_id',
        'hifz_program_id',
        'academic_year_id',
        'dual_write',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'dual_write' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }
}
