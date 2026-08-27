<?php

namespace App\Domains\Courses\Components\Quran\Enums;

enum QuranSessionOverallStatus: string
{
    case Excellent = 'excellent';
    case Good = 'good';
    case NeedsRevision = 'needs_revision';
    case Weak = 'weak';
    case Absent = 'absent';
}
