<?php

namespace App\Domains\PrayerTimes\Actions;

use App\Domains\PrayerTimes\Models\PrayerBroadcast;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListPrayerBroadcastsAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PrayerBroadcast>
     */
    public function execute(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        $query = PrayerBroadcast::query()->with('island')->latest('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['mode'])) {
            $query->where('mode', $filters['mode']);
        }
        if (! empty($filters['date'])) {
            $query->whereDate('created_at', $filters['date']);
        }

        return $query->paginate($perPage);
    }

    /**
     * @return list<array<int|string|null>>
     */
    public function csvRows(array $filters = []): array
    {
        $rows = [];
        PrayerBroadcast::query()
            ->with('island')
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['mode'] ?? null, fn ($q, $mode) => $q->where('mode', $mode))
            ->latest('id')
            ->get()
            ->each(function (PrayerBroadcast $row) use (&$rows): void {
                $rows[] = [
                    $row->id,
                    $row->mode->value,
                    $row->status->value,
                    $row->island?->name_latin,
                    $row->date_from?->toDateString(),
                    $row->date_to?->toDateString(),
                    $row->sent_count,
                    $row->failed_count,
                    $row->estimated_cost,
                    $row->created_at?->toDateTimeString(),
                ];
            });

        return $rows;
    }
}
