<?php

namespace App\Domains\HR\Enums;

enum StaffAttendanceSource: string
{
    case Manual = 'manual';
    case Self = 'self';
    case External = 'external';
    case Import = 'import';
}
