<?php

namespace App\Domains\Offerings\Enums;

enum OfferingStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';
}
