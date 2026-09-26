<?php

namespace App\Domains\Bookshop\Enums;

/**
 * BOOKSHOP_PLAN §5: shop-wide (every listing, search, the vendor's page) or
 * only on the vendor's own storefront.
 */
enum ProductVisibility: string
{
    case Shop = 'shop';
    case Storefront = 'storefront';
}
