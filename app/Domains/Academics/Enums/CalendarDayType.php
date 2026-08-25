<?php

namespace App\Domains\Academics\Enums;

enum CalendarDayType: string
{
    case Holiday = 'holiday';
    case Event = 'event';
    case ExamDay = 'exam_day';
    case Closure = 'closure';
    case SpecialSchedule = 'special_schedule';
}
