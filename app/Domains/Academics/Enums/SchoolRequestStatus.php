<?php

namespace App\Domains\Academics\Enums;

enum SchoolRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
