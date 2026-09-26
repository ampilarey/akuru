<?php

namespace App\Domains\Bookshop\Enums;

/** A suspended vendor's members cannot use the portal; its products leave the shop. */
enum VendorStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
