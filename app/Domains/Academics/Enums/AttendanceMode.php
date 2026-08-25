<?php

namespace App\Domains\Academics\Enums;

enum AttendanceMode: string
{
    case PerLesson = 'per_lesson';
    case Daily = 'daily';
}
