<?php

namespace App\Domains\Commerce\Enums;

/**
 * LIBRARY_PLAN §35.6.
 */
enum GiftCardStatus: string
{
    case Active = 'active';
    case Redeemed = 'redeemed';
    case PartiallyUsed = 'partially_used';
    case Empty = 'empty';
    case Expired = 'expired';
    case Deactivated = 'deactivated';
}
