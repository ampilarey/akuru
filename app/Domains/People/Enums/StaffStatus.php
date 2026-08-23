<?php

namespace App\Domains\People\Enums;

enum StaffStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Ended = 'ended';
}
