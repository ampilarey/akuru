<?php

namespace App\Domains\Academics\Enums;

enum TermStatus: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Closed = 'closed';
}
