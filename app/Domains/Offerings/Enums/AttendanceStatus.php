<?php

namespace App\Domains\Offerings\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';
    case Pending = 'pending';
}
