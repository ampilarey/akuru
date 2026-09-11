<?php

namespace App\Domains\People\Enums;

enum SensitiveNoteCategory: string
{
    case Medical = 'medical';
    case Dietary = 'dietary';
    case Welfare = 'welfare';
    case Access = 'access';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Medical => 'Medical',
            self::Dietary => 'Dietary',
            self::Welfare => 'Welfare',
            self::Access => 'Access and learning support',
            self::Other => 'Other',
        };
    }
}
