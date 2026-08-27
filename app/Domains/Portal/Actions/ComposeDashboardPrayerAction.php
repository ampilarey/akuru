<?php

namespace App\Domains\Portal\Actions;

use App\Domains\PrayerTimes\Contracts\PrayerTimeProviderInterface;
use App\Domains\Settings\Actions\GetSettingAction;
use App\Support\Services\IslamicCalendarService;

class ComposeDashboardPrayerAction
{
    /**
     * @return array{islamicDate: array<string, mixed>, prayerTimes: array<string, string|null>, currentPrayer: array{prayer: ?string, time: mixed, is_prayer_time: bool}, specialDays: list<string>}
     */
    public function execute(): array
    {
        $islamicDate = IslamicCalendarService::getCurrentIslamicDate();
        $specialDays = IslamicCalendarService::getSpecialIslamicDays();
        $provider = app(PrayerTimeProviderInterface::class);
        $islandId = (int) app(GetSettingAction::class)->execute('prayer.default_island_id', 0);
        if ($islandId < 1) {
            $islandId = (int) ($provider->listIslands(true)->first()?->id ?? 0);
        }

        $prayerTimes = [];
        $currentPrayer = ['prayer' => null, 'time' => null, 'is_prayer_time' => false];
        if ($islandId > 0) {
            $dto = $provider->resolveForIsland($islandId, now()->timezone(config('app.timezone')));
            if ($dto->available) {
                $prayerTimes = $dto->times();
                $currentPrayer = $dto->currentPrayer();
            }
        }

        return compact('islamicDate', 'prayerTimes', 'currentPrayer', 'specialDays');
    }
}
