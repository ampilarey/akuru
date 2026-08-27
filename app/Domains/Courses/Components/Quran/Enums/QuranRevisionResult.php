<?php

namespace App\Domains\Courses\Components\Quran\Enums;

enum QuranRevisionResult: string
{
    case Pass = 'pass';
    case Repeat = 'repeat';
    case NotDone = 'not_done';
}
