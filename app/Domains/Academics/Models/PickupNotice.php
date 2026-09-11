<?php

namespace App\Domains\Academics\Models;

use App\Domains\Academics\Enums\PickupStatus;
use Illuminate\Database\Eloquent\Model;

class PickupNotice extends Model
{
    protected $fillable = [
        'academic_year_id', 'student_id', 'guardian_user_id', 'date', 'status',
        'requested_at', 'sent_at', 'sent_by', 'collected_at', 'cancelled_at', 'note',
    ];

    protected $casts = [
        'date' => 'date',
        'status' => PickupStatus::class,
        'requested_at' => 'datetime',
        'sent_at' => 'datetime',
        'collected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}
