<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\DTOs\IslandDTO;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\Settings\Actions\GetSettingAction;

class ResolveDefaultPrayerIslandAction
{
    public function execute(): ?int
    {
        $configured = (int) app(GetSettingAction::class)->execute('prayer.default_island_id', 0);
        if ($configured > 0 && PrayerIsland::query()->whereKey($configured)->exists()) {
            return $configured;
        }

        $male = PrayerIsland::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('name_latin', 'Malé')
                    ->orWhere('name_latin', 'Male')
                    ->orWhere('name_latin', 'like', 'Malé%');
            })
            ->value('id');

        if ($male !== null) {
            return (int) $male;
        }

        $first = PrayerIsland::query()->where('is_active', true)->orderBy('id')->value('id');

        return $first !== null ? (int) $first : null;
    }

    public function island(): ?IslandDTO
    {
        $id = $this->execute();
        if ($id === null) {
            return null;
        }

        $row = PrayerIsland::query()->find($id);
        if ($row === null) {
            return null;
        }

        return app(FindNearestIslandAction::class)->toDto($row);
    }
}
