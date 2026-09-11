<?php

namespace App\Domains\Circulation\Enums;

enum LoanStatus: string
{
    case Out = 'out';
    case Returned = 'returned';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Out => 'Out',
            self::Returned => 'Returned',
            self::Lost => 'Lost',
        };
    }
}
