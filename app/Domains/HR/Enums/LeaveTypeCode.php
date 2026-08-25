<?php

namespace App\Domains\HR\Enums;

enum LeaveTypeCode: string
{
    case Annual = 'annual';
    case Sick = 'sick';
    case Family = 'family';
    case HajjUmrah = 'hajj_umrah';
    case Maternity = 'maternity';
    case Paternity = 'paternity';
    case Unpaid = 'unpaid';
    case Other = 'other';
}
