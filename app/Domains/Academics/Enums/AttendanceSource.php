<?php

namespace App\Domains\Academics\Enums;

enum AttendanceSource: string
{
    case Register = 'register';
    case Daily = 'daily';
    case External = 'external';
    case Import = 'import';
}
