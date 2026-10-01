<?php

namespace App\Domains\Lending\Enums;

/**
 * A loan's life (LENDING_AND_USED_BOOKS_PLAN L1): asked for; accepted or
 * declined by the lender, or cancelled by the borrower before handover;
 * out once handed over; returned when the lender says it is back.
 */
enum LoanStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Out = 'out';
    case Returned = 'returned';

    /** Still in play: the book is spoken for. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Accepted, self::Out], true);
    }

    public function label(): string
    {
        return __('lending.loan_status_'.$this->value);
    }
}
