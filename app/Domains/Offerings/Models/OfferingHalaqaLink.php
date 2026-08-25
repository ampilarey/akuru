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
    ];

    public function offering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class, 'course_offering_id');
    }
}
