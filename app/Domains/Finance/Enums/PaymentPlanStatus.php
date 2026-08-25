<?php

namespace App\Domains\Finance\Enums;

enum PaymentPlanStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Defaulted = 'defaulted';
    case Cancelled = 'cancelled';
}
