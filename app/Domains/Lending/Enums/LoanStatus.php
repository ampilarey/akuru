<?php

namespace App\Domains\Lending\Enums;

/**
 * A loan's life (LENDING_AND_USED_BOOKS_PLAN L1): asked for; accepted or
 * declined by the lender, or cancelled by the borrower before handover;
 * out once handed over; returned when the lender says it is back — or, for
 * a give-away (L3), given at handover and closed there.
 */
enum LoanStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Out = 'out';
    case Returned = 'returned';
    /** L3: a give-away handed over — the book is theirs now; nothing comes back. */
    case Given = 'given';

    /** Still in play: the book is spoken for. */
    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Accepted, self::Out], true);
    }

    /** Done, with the book in the other person's hands: both sides may rate (L2/L3). */
    public function isClosedWell(): bool
    {
        return in_array($this, [self::Returned, self::Given], true);
    }

    public function label(): string
    {
        return __('lending.loan_status_'.$this->value);
    }
}
