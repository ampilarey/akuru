<?php

namespace App\Domains\HR\Enums;

enum PayrollPeriodStatus: string
{
    case Open = 'open';
    case Processing = 'processing';
    case Review = 'review';
    case Approved = 'approved';
    case Paid = 'paid';
    case Locked = 'locked';
}
