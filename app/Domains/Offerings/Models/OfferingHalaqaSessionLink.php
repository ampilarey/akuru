<?php

namespace App\Domains\Offerings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferingHalaqaSessionLink extends Model
{
    protected $fillable = [
        'course_offering_session_id',
        'hifz_session_id',
        'academic_year_id',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(CourseOfferingSession::class, 'course_offering_session_id');
    }
}
