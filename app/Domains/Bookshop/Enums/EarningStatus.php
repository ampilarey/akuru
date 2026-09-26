<?php

namespace App\Domains\Bookshop\Enums;

/**
 * A vendor earning's life (BOOKSHOP_PLAN §5 "Money"): pending until the
 * order is delivered and its return window has passed, then available for
 * a payout, then paid; reversed when the whole sale went back.
 */
enum EarningStatus: string
{
    case Pending = 'pending';
    case Available = 'available';
    case Paid = 'paid';
    case Reversed = 'reversed';
}
