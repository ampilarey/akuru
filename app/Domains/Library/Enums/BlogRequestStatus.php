<?php

namespace App\Domains\Library\Enums;

/** BLOG_PLAN BL1: where a student's *start a blog* request stands. */
enum BlogRequestStatus: string
{
    case WaitingParent = 'waiting_parent';
    case WaitingOffice = 'waiting_office';
    case Approved = 'approved';
    case Refused = 'refused';
    case ParentDeclined = 'parent_declined';

    /** A request still being decided: a student may hold only one. */
    public function isOpen(): bool
    {
        return $this === self::WaitingParent || $this === self::WaitingOffice;
    }
}
