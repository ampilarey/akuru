<?php

namespace App\Domains\Bookshop\Enums;

/**
 * Why an item is going back. The first three are the shop's fault (the
 * Delivery and Returns page: faulty or not as described is replaced or
 * refunded; the shop pays return delivery, and the delivery fee goes
 * back); the last two are the buyer's.
 */
enum ReturnReason: string
{
    case Damaged = 'damaged';
    case WrongItem = 'wrong_item';
    case NotAsDescribed = 'not_as_described';
    case ChangedMind = 'changed_mind';
    case Other = 'other';

    public function shopsFault(): bool
    {
        return in_array($this, [self::Damaged, self::WrongItem, self::NotAsDescribed], true);
    }
}
