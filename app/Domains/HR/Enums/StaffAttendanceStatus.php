<?php

namespace App\Domains\HR\Enums;

enum StaffAttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case HalfDay = 'half_day';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';
}
