<?php

namespace App\Enums\Hifz;

enum HifzRevisionResult: string
{
    case Pass = 'pass';
    case Repeat = 'repeat';
    case NotDone = 'not_done';
}
