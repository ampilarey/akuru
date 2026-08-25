<?php

namespace App\Domains\Progress\Enums;

enum AssessmentAttemptStatus: string
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Scored = 'scored';
}
