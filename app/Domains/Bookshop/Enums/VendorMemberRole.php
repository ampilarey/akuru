<?php

namespace App\Domains\Bookshop\Enums;

/**
 * BOOKSHOP_PLAN §3: the owner runs everything including members and money;
 * staff handle products, stock and orders.
 */
enum VendorMemberRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
}
