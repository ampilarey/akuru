<?php

namespace App\Domains\Academics\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Excused = 'excused';
    case LeftEarly = 'left_early';
}
