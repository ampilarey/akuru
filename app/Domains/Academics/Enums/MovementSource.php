<?php

namespace App\Domains\Academics\Enums;

/**
 * What recorded the movement.
 *
 * `Manual` is the only value anything writes today. `Card` and `Qr` exist so
 * that the day hardware is bought, the adapter has a value to pass and the
 * console can say *"recorded at the gate"* rather than blaming a member of
 * staff who was not there.
 */
enum MovementSource: string
{
    case Manual = 'manual';
    case Card = 'card';
    case Qr = 'qr';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'By hand',
            self::Card => 'Card',
            self::Qr => 'QR',
        };
    }
}
