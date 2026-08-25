<?php

namespace App\Domains\Academics\Enums;

enum LessonLogStatus: string
{
    case Expected = 'expected';
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Locked = 'locked';
}
