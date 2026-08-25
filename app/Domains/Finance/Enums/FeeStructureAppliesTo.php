<?php

namespace App\Domains\Finance\Enums;

enum FeeStructureAppliesTo: string
{
    case SelectedClasses = 'class';
    case AllClasses = 'all_classes';
}
