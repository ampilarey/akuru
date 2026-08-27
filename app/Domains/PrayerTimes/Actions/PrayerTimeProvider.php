<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Contracts\PrayerTimeProviderInterface;
use App\Domains\PrayerTimes\DTOs\IslandDTO;
use App\Domains\PrayerTimes\DTOs\PrayerRangeComparisonDTO;
use App\Domains\PrayerTimes\DTOs\PrayerTimesDTO;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PrayerTimeProvider implements PrayerTimeProviderInterface
{
    public function resolveForIsland(int $islandId, Carbon $date): PrayerTimesDTO
    {
        return app(ResolvePrayerTimesForIslandAction::class)->execute($islandId, $date);
    }

    public function listIslands(?bool $activeOnly = true): Collection
    {
        return app(ListPrayerIslandsAction::class)->execute($activeOnly);
    }

    public function findNearestIsland(float $latitude, float $longitude): IslandDTO
    {
        return app(FindNearestIslandAction::class)->execute($latitude, $longitude);
    }

    public function compareRange(int $islandId, Carbon $from, Carbon $to): PrayerRangeComparisonDTO
    {
        return app(ComparePrayerTimesAcrossRangeAction::class)->execute($islandId, $from, $to);
    }
}
