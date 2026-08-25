<?php

namespace App\Domains\HR\Enums;

enum PayslipStatus: string
{
    case Draft = 'draft';
    case Final = 'final';
}
