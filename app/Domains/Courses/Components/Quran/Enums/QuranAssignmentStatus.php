<?php

namespace App\Domains\Courses\Components\Quran\Enums;

/**
 * SPEC §52.18 assignment statuses.
 */
enum QuranAssignmentStatus: string
{
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case NeedsRepeat = 'needs_repeat';
    case Passed = 'passed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
