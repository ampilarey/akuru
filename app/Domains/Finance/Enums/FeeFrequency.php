<?php

namespace App\Domains\Finance\Enums;

enum FeeFrequency: string
{
    case OneTime = 'one_time';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semester = 'semester';
    case Annual = 'annual';
}
