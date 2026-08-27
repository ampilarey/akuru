<?php

namespace App\Domains\Website\Actions;

use App\Domains\PrayerTimes\Contracts\PrayerTimeProviderInterface;
use App\Domains\Settings\Actions\GetSettingAction;
use App\Support\Services\IslamicCalendarService;

class ComposeHomepagePrayerAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(): ?array
    {
        if (! app(GetSettingAction::class)->execute('prayer.public_page_enabled', true)) {
            return null;
        }

        $provider = app(PrayerTimeProviderInterface::class);
        $islandId = (int) app(GetSettingAction::class)->execute('prayer.default_island_id', 0);
        if ($islandId < 1) {
            $islandId = (int) ($provider->listIslands(true)->first()?->id ?? 0);
        }
        if ($islandId < 1) {
            return null;
        }

        $dto = $provider->resolveForIsland($islandId, now()->timezone(config('app.timezone')));
        if (! $dto->available) {
            return null;
        }

        return $dto->toArray() + [
            'hijri' => IslamicCalendarService::gregorianToHijri($dto->date),
        ];
    }
}
