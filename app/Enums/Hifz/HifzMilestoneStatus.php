<?php

namespace App\Enums\Hifz;

enum HifzMilestoneStatus: string
{
    case Pending = 'pending';
    case SupervisorReviewed = 'supervisor_reviewed';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
