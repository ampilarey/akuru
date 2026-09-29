<?php

namespace App\Domains\Bookshop\Enums;

/**
 * A suspended vendor's members cannot use the portal; its products leave the
 * shop. A paused vendor's products leave the shop too, but its members keep
 * the portal, so the orders already paid can still be prepared, dispatched
 * and delivered (STATUS §5lo) — stop selling, finish delivering.
 */
enum VendorStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Suspended = 'suspended';

    /** The statuses whose members may open the vendor portal. */
    public static function portalOpen(): array
    {
        return [self::Active->value, self::Paused->value];
    }
}
