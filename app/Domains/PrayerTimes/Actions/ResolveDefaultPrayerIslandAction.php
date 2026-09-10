<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\DTOs\IslandDTO;
use App\Domains\PrayerTimes\Models\PrayerIsland;
use App\Domains\Settings\Actions\GetSettingAction;

/**
 * Which island's prayer times to show when nobody has chosen one.
 *
 * This action existed and **nothing called it**, while four places answered the
 * same question differently:
 *
 *  - here: the setting (validated), else Malé by `name_latin`, else first active;
 *  - `ImportController::ensureDefaultIsland`: the setting (validated), else Malé
 *    by its **Dhivehi** name `މާލެ`, else first active — and it *writes* the
 *    setting;
 *  - `ComposeDashboardPrayerAction` and the public `PrayerTimesController`: the
 *    setting **unvalidated**, else `listIslands()->first()`.
 *
 * Two real consequences. `listIslands()` orders by atoll then name, so its
 * "first" is the alphabetically-first atoll — never Malé, and the Maldives is
 * wide enough that another atoll's times are wrong by minutes. And because
 * neither reader validated the setting, an island that was deleted or
 * deactivated left `resolveForIsland()` with a dead id: prayer times silently
 * blanked on the dashboard and the public page rather than falling back.
 *
 * One rule now, holding the better half of each (rule 11): the configured
 * island **if it still exists and is active**, else Malé by either name, else
 * the first active island.
 */
class ResolveDefaultPrayerIslandAction
{
    public function execute(): ?int
    {
        $configured = (int) app(GetSettingAction::class)->execute('prayer.default_island_id', 0);

        if ($configured > 0 && $this->isUsable($configured)) {
            return $configured;
        }

        return $this->male() ?? $this->firstActive();
    }

    public function island(): ?IslandDTO
    {
        $id = $this->execute();
        if ($id === null) {
            return null;
        }

        $row = PrayerIsland::query()->find($id);

        return $row === null ? null : app(FindNearestIslandAction::class)->toDto($row);
    }

    /**
     * A configured island that has been deactivated is as unusable as one that
     * has been deleted — it has no current times to show.
     */
    private function isUsable(int $islandId): bool
    {
        return PrayerIsland::query()
            ->whereKey($islandId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Matched on both names on purpose: the importer knew it as `މާލެ` and this
     * action knew it as `Malé`, so each found it only on datasets the other
     * would have missed.
     */
    private function male(): ?int
    {
        $id = PrayerIsland::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->where('name', 'މާލެ')
                    ->orWhere('name_latin', 'Malé')
                    ->orWhere('name_latin', 'Male')
                    ->orWhere('name_latin', 'like', 'Malé%');
            })
            ->orderBy('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    private function firstActive(): ?int
    {
        $id = PrayerIsland::query()->where('is_active', true)->orderBy('id')->value('id');

        return $id === null ? null : (int) $id;
    }
}
