<?php

namespace App\Domains\Courses\Components\Quran\Enums;

enum RevisionScheduleStatus: string
{
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Missed = 'missed';
    case Cancelled = 'cancelled';
}
