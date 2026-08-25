<?php

namespace App\Domains\HR\Enums;

enum AppraisalStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Acknowledged = 'acknowledged';
}
