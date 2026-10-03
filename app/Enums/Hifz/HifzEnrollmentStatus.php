<?php

namespace App\Enums\Hifz;

enum HifzEnrollmentStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Transferred = 'transferred';
    // C16 slice N4 (OWNER_ACTIONS 15): the pupil has left the Institute.
    // Until this value a departure had no word — `transferred` most
    // naturally means moved to another halaqa.
    case Withdrawn = 'withdrawn';

    /** The ways an enrolment ends; `paused` is not an ending. */
    public static function endings(): array
    {
        return [self::Withdrawn, self::Transferred, self::Completed];
    }

    public function isEnded(): bool
    {
        return in_array($this, self::endings(), true);
    }
}
