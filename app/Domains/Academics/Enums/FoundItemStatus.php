<?php

namespace App\Domains\Academics\Enums;

enum FoundItemStatus: string
{
    case Listed = 'listed';
    case Returned = 'returned';
}
