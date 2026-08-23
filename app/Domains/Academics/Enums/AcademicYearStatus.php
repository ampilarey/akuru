<?php

namespace App\Domains\Academics\Enums;

enum AcademicYearStatus: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Closed = 'closed';
}
