<?php

namespace App\Domains\Courses\Enums;

enum AssessmentStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
