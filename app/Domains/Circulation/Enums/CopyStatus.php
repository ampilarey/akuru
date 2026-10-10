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
        // In the page's language (slice LD1); the pages name the code themselves.
        return match ($this) {
            self::Available => __('circulation.copy_status_available'),
            self::OnLoan => __('circulation.copy_status_on_loan'),
            self::Lost => __('circulation.copy_status_lost'),
            self::Withdrawn => __('circulation.copy_status_withdrawn'),
        };
    }

    /** Withdrawn and lost copies are not lendable; on-loan ones are already out. */
    public function isLendable(): bool
    {
        return $this === self::Available;
    }
}
