<?php

namespace App\Domains\HR\Models;

use Illuminate\Database\Eloquent\Model;

class LessonObservation extends Model
{
    protected $fillable = [
        'staff_profile_id',
        'observer_id',
        'date',
        'class_id',
        'subject_id',
        'criteria',
        'summary',
        'shared_with_staff',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'criteria' => 'array',
            'shared_with_staff' => 'boolean',
        ];
    }
}
