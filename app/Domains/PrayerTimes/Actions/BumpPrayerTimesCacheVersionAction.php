<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\Settings\Actions\GetSettingAction;
use App\Domains\Settings\Actions\SetSettingAction;

class BumpPrayerTimesCacheVersionAction
{
    public function execute(): int
    {
        $current = (int) app(GetSettingAction::class)->execute('prayer_times_cache_version', 1);
        $next = max(1, $current) + 1;
        app(SetSettingAction::class)->execute(
            'prayer_times_cache_version',
            (string) $next,
            'string',
            'prayer',
            'Prayer times cache version',
        );

        return $next;
    }
}
