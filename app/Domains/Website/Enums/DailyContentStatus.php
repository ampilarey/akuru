<?php

namespace App\Domains\Website\Enums;

enum DailyContentStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';
    case Archived = 'archived';
}
