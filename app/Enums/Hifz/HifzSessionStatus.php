<?php

namespace App\Enums\Hifz;

enum HifzSessionStatus: string
{
    case Draft = 'draft';
    case Completed = 'completed';
    case Reviewed = 'reviewed';
    case Locked = 'locked';
}
