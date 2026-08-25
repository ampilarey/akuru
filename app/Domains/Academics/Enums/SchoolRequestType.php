<?php

namespace App\Domains\Academics\Enums;

enum SchoolRequestType: string
{
    case TeacherLeave = 'teacher_leave';
    case StaffLeave = 'staff_leave';
    case ParentGeneral = 'parent_general';
    case ScheduleChange = 'schedule_change';
    case Other = 'other';
}
