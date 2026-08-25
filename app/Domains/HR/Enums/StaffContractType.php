<?php

namespace App\Domains\HR\Enums;

enum StaffContractType: string
{
    case Permanent = 'permanent';
    case FixedTerm = 'fixed_term';
    case PartTime = 'part_time';
    case Consultant = 'consultant';
}
