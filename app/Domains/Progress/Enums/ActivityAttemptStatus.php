<?php

namespace App\Domains\Progress\Enums;

enum ActivityAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Scored = 'scored';
}
