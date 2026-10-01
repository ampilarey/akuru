<?php

namespace App\Domains\Lending\Enums;

enum LendingBookStatus: string
{
    case Available = 'available';
    case OnLoan = 'on_loan';
    case Paused = 'paused';
    case Removed = 'removed';
    /** L3: a give-away that has found its new owner. */
    case Given = 'given';

    public function label(): string
    {
        return __('lending.book_status_'.$this->value);
    }
}
