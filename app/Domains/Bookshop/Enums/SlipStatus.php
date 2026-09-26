<?php

namespace App\Domains\Bookshop\Enums;

/** A bank-transfer slip waits for the office, then is confirmed or rejected. */
enum SlipStatus: string
{
    case Waiting = 'waiting';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
