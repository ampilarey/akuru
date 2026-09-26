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
    // B9b: placed, stock taken, the cash is paid to the shop on delivery.
    case CashOnDelivery = 'cash_on_delivery';
    case Expired = 'expired';
    case Failed = 'failed';
}
