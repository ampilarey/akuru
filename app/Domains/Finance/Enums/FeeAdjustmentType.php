<?php

namespace App\Domains\Finance\Enums;

enum FeeAdjustmentType: string
{
    case SiblingDiscount = 'sibling_discount';
    case Scholarship = 'scholarship';
    case StaffChild = 'staff_child';
    case HardshipWaiver = 'hardship_waiver';
    case Other = 'other';
}
