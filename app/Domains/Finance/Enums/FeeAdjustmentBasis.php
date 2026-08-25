<?php

namespace App\Domains\Finance\Enums;

enum FeeAdjustmentBasis: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
