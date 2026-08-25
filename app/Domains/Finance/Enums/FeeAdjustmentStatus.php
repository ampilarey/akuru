<?php

namespace App\Domains\Finance\Enums;

enum FeeAdjustmentStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Revoked = 'revoked';
}
