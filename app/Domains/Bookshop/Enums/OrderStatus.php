<?php

namespace App\Domains\Bookshop\Enums;

/**
 * An order's life (BOOKSHOP_PLAN §4 "After the order"): pending payment →
 * paid → processing → ready to collect *or* dispatched → delivered (which a
 * collection order reads as "collected"). `needs_attention` is a paid order
 * whose stock ran out between reservation lapse and the webhook — never a
 * silent oversell (plan §8); the vendor carries on or cancels it.
 * `cancelled` is before dispatch only, with money going back.
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case NeedsAttention = 'needs_attention';
    case Processing = 'processing';
    case Ready = 'ready';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /** Money is in and the parcel has not left: the shop may still cancel. */
    public function cancellableByVendor(): bool
    {
        return in_array($this, [self::Paid, self::NeedsAttention, self::Processing, self::Ready], true);
    }

    /** "Cancel before dispatch" (plan §4). A parcel waiting to be collected has not left the shop. */
    public function cancellableByCustomer(): bool
    {
        return $this->cancellableByVendor();
    }

    /** The statuses a vendor works through, in order, for the queue's tabs. */
    public static function queue(): array
    {
        return [self::Paid, self::NeedsAttention, self::Processing, self::Ready, self::Dispatched, self::Delivered, self::Cancelled];
    }
}
