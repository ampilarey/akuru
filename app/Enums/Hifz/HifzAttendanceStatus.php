<?php

namespace App\Enums\Hifz;

enum HifzAttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Excused = 'excused';
}
