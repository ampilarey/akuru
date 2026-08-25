<?php

namespace App\Domains\Academics\Enums;

enum RoomType: string
{
    case Classroom = 'classroom';
    case Lab = 'lab';
    case Hall = 'hall';
    case Online = 'online';
    case Other = 'other';
}
