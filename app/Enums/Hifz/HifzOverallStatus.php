<?php

namespace App\Enums\Hifz;

enum HifzOverallStatus: string
{
    case Excellent = 'excellent';
    case Good = 'good';
    case NeedsRevision = 'needs_revision';
    case Weak = 'weak';
    case Absent = 'absent';
}
