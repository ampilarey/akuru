<?php

namespace App\Domains\Academics\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One move of a photo from one pupil to another.
 *
 * Append-only in practice: nothing updates or deletes these rows, because the
 * whole point is answering "which family saw this, and when did it stop?".
 */
class StudentWorkReassignment extends Model
{
    protected $fillable = [
        'student_work_id',
        'from_student_id',
        'to_student_id',
        'moved_by',
    ];
}
