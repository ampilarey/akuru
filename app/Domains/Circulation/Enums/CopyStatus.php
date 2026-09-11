<?php

namespace App\Domains\Circulation\Enums;

enum CopyStatus: string
{
    case Available = 'available';
    case OnLoan = 'on_loan';
    case Lost = 'lost';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'On the shelf',
            self::OnLoan => 'On loan',
            self::Lost => 'Lost',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /** Withdrawn and lost copies are not lendable; on-loan ones are already out. */
    public function isLendable(): bool
    {
        return $this === self::Available;
    }
}
