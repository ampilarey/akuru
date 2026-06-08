<?php

namespace App\Enums\Hifz;

enum HifzEnrollmentStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Transferred = 'transferred';
}
