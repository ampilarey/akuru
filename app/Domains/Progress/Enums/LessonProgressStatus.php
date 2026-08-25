<?php

namespace App\Domains\Progress\Enums;

enum LessonProgressStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
}
