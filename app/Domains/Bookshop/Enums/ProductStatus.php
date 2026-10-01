<?php

namespace App\Domains\Bookshop\Enums;

/**
 * Only `active` products are for sale (B1b shows them; B2 sells them).
 * `pending_review` (COMMERCE_PARITY_PLAN P4): the shop asked to sell it and
 * the office has not approved it yet — not shown, not sold.
 */
enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
    case PendingReview = 'pending_review';
}
