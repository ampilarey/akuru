<?php

namespace App\Domains\Courses\Enums;

enum LessonStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
