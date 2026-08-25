<?php

namespace App\Domains\HR\Models;

use App\Domains\HR\Enums\StaffAttendanceSource;
use App\Domains\HR\Enums\StaffAttendanceStatus;
use Illuminate\Database\Eloquent\Model;

class StaffAttendance extends Model
{
    protected $table = 'staff_attendance';

    protected $fillable = [
        'staff_profile_id',
        'academic_year_id',
        'date',
        'check_in',
        'check_out',
        'status',
        'source',
        'minutes_late',
        'marked_by',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'status' => StaffAttendanceStatus::class,
            'source' => StaffAttendanceSource::class,
        ];
    }
}
