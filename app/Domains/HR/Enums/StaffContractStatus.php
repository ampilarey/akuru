<?php

namespace App\Domains\HR\Enums;

enum StaffContractStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Terminated = 'terminated';
    case Superseded = 'superseded';
}
