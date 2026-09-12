<?php

namespace App\Domains\Library\Enums;

/**
 * The three patterns LIBRARY_PLAN §9.2 names. Each is a **question**, not an
 * accusation: a reader skimming a reference book legitimately turns pages fast,
 * and a family sharing one account across a phone and a laptop is not the same
 * thing as a book being resold.
 */
enum LibraryReadingSignal: string
{
    /** Pages opened faster than a person reads them — "detect rapid page opening". */
    case RapidPages = 'rapid_pages';

    /** One account reading from more distinct devices than a household plausibly has. */
    case ManyDevices = 'many_devices';

    /** More reading sessions open at once than the configured limit. */
    case ConcurrentSessions = 'concurrent_sessions';

    public function label(): string
    {
        return match ($this) {
            self::RapidPages => 'Pages opened unusually fast',
            self::ManyDevices => 'Many devices on one account',
            self::ConcurrentSessions => 'Several sessions at once',
        };
    }
}
