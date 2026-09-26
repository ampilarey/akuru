<?php

namespace App\Domains\Bookshop\Enums;

/**
 * Money going back (plan audit finding 6). Wallet and bank-transfer money
 * goes back to the wallet at once (`done`); card money waits (`pending`)
 * for the office to return it through BML and record it.
 */
enum RefundStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
}
