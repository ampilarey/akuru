<?php

namespace App\Domains\Offerings\Enums;

enum AttendanceMode: string
{
    case Physical = 'physical';
    case Online = 'online';
    case NotApplicable = 'not_applicable';
}
