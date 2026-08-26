<?php

namespace App\Domains\Notifications\Support;

final class LiveSms
{
    /**
     * Live HTTP only when APP_ENV=production and services.sms.live is an
     * explicit true. Missing, unknown, or stringy "false" values fail closed.
     */
    public static function allowed(): bool
    {
        if (! app()->environment('production')) {
            return false;
        }

        return self::flagIsTrue(config('services.sms.live'));
    }

    private static function flagIsTrue(mixed $value): bool
    {
        if ($value === true || $value === 1) {
            return true;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['1', 'true'], true);
        }

        return false;
    }
}
