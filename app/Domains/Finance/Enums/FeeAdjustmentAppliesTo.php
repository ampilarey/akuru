<?php

namespace App\Domains\Finance\Enums;

enum FeeAdjustmentAppliesTo: string
{
    case AllItems = 'all_items';
    case ItemTypes = 'item_types';
}
