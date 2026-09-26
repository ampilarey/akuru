<?php

namespace App\Domains\Bookshop\Enums;

/** A customer's request to send an item back (BOOKSHOP_PLAN §5 "accept or decline a return request"). */
enum ReturnStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Declined = 'declined';
}
