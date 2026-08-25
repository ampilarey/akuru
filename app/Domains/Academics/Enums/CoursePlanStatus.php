<?php

namespace App\Domains\Academics\Enums;

enum CoursePlanStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
