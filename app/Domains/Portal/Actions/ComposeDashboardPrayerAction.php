<?php

namespace App\Domains\Portal\Actions;

use App\Domains\PrayerTimes\Actions\ResolveDefaultPrayerIslandAction;
use App\Domains\PrayerTimes\Contracts\PrayerTimeProviderInterface;
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
        // One rule for "which island", in PrayerTimes (rule 11). This used to
        // read the setting unvalidated and fall back to the first island by
        // atoll — never Malé, and a deleted island blanked the tile entirely.
        $islandId = (int) (app(ResolveDefaultPrayerIslandAction::class)->execute() ?? 0);

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
