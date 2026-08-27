<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\DTOs\IslandDTO;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use Illuminate\Support\Collection;

class ListPrayerIslandsAction
{
    /**
     * @return Collection<int, IslandDTO>
     */
    public function execute(?bool $activeOnly = true): Collection
    {
        $query = PrayerIsland::query()->orderBy('atoll_latin')->orderBy('name_latin');
        if ($activeOnly) {
            $query->where('is_active', true);
        }

        $mapper = app(FindNearestIslandAction::class);

        return $query->get()->map(fn (PrayerIsland $island) => $mapper->toDto($island))->values();
    }
}
