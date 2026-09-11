<?php

namespace App\Domains\Academics\Enums;

enum MovementDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Arrived',
            self::Out => 'Left',
        };
    }

    public function opposite(): self
    {
        return match ($this) {
            self::In => self::Out,
            self::Out => self::In,
        };
    }
}
