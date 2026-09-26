<?php

namespace App\Domains\Bookshop\Enums;

/**
 * B2 writes these four. B3 (fulfilment) adds processing, ready, dispatched,
 * delivered and cancelled when it writes them. `needs_attention` is a paid
 * order whose stock ran out between reservation lapse and the webhook —
 * never a silent oversell (plan §8).
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case NeedsAttention = 'needs_attention';
    case Expired = 'expired';
}
