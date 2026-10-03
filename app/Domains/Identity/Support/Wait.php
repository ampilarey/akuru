<?php

namespace App\Domains\Identity\Support;

/**
 * A wait, said the way a person would (BACKLOG C16 slice N3). The OTP
 * limits used to say "try again in 1 minutes" — `ceil($seconds / 60)` and
 * a fixed plural — and a 45-second cooldown read as a minute.
 */
final class Wait
{
    public static function describe(int $seconds): string
    {
        $seconds = max(1, $seconds);
        if ($seconds < 60) {
            return $seconds === 1 ? '1 second' : $seconds.' seconds';
        }
        $minutes = (int) ceil($seconds / 60);

        return $minutes === 1 ? '1 minute' : $minutes.' minutes';
    }
}
