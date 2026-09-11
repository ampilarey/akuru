<?php

namespace App\Domains\Academics\Enums;

enum PickupStatus: string
{
    case Requested = 'requested';
    case Sent = 'sent';
    case Collected = 'collected';
    case Cancelled = 'cancelled';
}
