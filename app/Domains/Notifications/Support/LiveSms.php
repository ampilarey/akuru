<?php

namespace App\Domains\Notifications\Support;

final class LiveSms
{
    public static function allowed(): bool
    {
        return app()->environment('production')
            && (bool) config('services.sms.live', false);
    }
}
