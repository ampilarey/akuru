<?php

namespace App\Domains\Academics\Enums;

enum MeetingSlotStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
}
