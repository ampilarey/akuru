<?php

namespace App\Domains\Bookshop\Enums;

/**
 * A checkout waits for money, gets it, or runs out of time. `failed` is a
 * card payment the gateway refused to start.
 */
enum CheckoutStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Expired = 'expired';
    case Failed = 'failed';
}
