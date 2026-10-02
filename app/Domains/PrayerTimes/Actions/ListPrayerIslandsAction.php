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

    /**
     * One page of the office's islands list, with a search by island or
     * atoll in either script (docs/ADMIN_PANEL.md §7 P5, STATUS §5no). All
     * 205 rows used to be sent and drawn at once; `execute()` still serves
     * the CSV and the broadcast form, which want them all.
     *
     * @return array{islands: list<array<string, mixed>>, pagination: array{current_page: int, last_page: int, total: int, prev: ?string, next: ?string}, filters: array{q: string}}
     */
    public function page(string $q = '', int $perPage = 25): array
    {
        $q = trim($q);
        $query = PrayerIsland::query()->orderBy('atoll_latin')->orderBy('name_latin');
        if ($q !== '') {
            $query->where(function ($where) use ($q) {
                $where->where('name_latin', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%")
                    ->orWhere('atoll_latin', 'like', "%{$q}%")
                    ->orWhere('atoll', 'like', "%{$q}%");
            });
        }

        $page = $query->paginate($perPage)->withQueryString();
        $mapper = app(FindNearestIslandAction::class);

        return [
            'islands' => collect($page->items())->map(fn (PrayerIsland $island) => $mapper->toDto($island)->toArray())->values()->all(),
            'pagination' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
            'filters' => ['q' => $q],
        ];
    }
}
