<?php

namespace App\Domains\Circulation\Enums;

enum LoanStatus: string
{
    case Out = 'out';
    case Returned = 'returned';
    case Lost = 'lost';

    public function label(): string
    {
        // In the page's language (slice LD1).
        return match ($this) {
            self::Out => __('circulation.loan_status_out'),
            self::Returned => __('circulation.loan_status_returned'),
            self::Lost => __('circulation.loan_status_lost'),
        };
    }
}
