<?php

namespace App\Domains\Academics\Enums;

enum MeetingBookingStatus: string
{
    case Booked = 'booked';
    case Cancelled = 'cancelled';
}
