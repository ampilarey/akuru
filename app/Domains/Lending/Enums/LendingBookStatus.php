<?php

namespace App\Domains\Lending\Enums;

enum LendingBookStatus: string
{
    case Available = 'available';
    case OnLoan = 'on_loan';
    case Paused = 'paused';
    case Removed = 'removed';

    public function label(): string
    {
        return __('lending.book_status_'.$this->value);
    }
}
