<?php

namespace App\Domains\Finance\Enums;

enum FeeStructureStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
}
