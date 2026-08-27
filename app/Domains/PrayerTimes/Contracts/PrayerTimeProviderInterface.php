<?php

namespace App\Domains\PrayerTimes\Contracts;

use App\Domains\PrayerTimes\DTOs\IslandDTO;
use App\Domains\PrayerTimes\DTOs\PrayerRangeComparisonDTO;
use App\Domains\PrayerTimes\DTOs\PrayerTimesDTO;
use Carbon\Carbon;
use Illuminate\Support\Collection;

interface PrayerTimeProviderInterface
{
    public function resolveForIsland(int $islandId, Carbon $date): PrayerTimesDTO;

    /**
     * @return Collection<int, IslandDTO>
     */
    public function listIslands(?bool $activeOnly = true): Collection;

    public function findNearestIsland(float $latitude, float $longitude): IslandDTO;

    public function compareRange(int $islandId, Carbon $from, Carbon $to): PrayerRangeComparisonDTO;
}
