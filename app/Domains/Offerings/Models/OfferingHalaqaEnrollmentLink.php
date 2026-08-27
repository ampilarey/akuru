<?php

namespace App\Domains\Offerings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * F2 mapping row: one legacy Hifz enrollment ↔ one engine course enrollment.
 * Integer Hifz id, no relation into Hifz (rule 3).
 */
class OfferingHalaqaEnrollmentLink extends Model
{
    protected $fillable = [
        'course_enrollment_id',
        'hifz_enrollment_id',
    ];
}
