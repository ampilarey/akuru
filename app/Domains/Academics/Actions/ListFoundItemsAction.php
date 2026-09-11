<?php

namespace App\Domains\Academics\Actions;

use App\Domains\Academics\Enums\FoundItemStatus;
use App\Domains\Academics\Models\AcademicYear;
use App\Domains\Academics\Models\FoundItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The list, for staff and for families.
 *
 * `$stillHereOnly` is what separates the two audiences rather than a second
 * action: a family looking for a lost coat wants what is still on the shelf,
 * while the office also needs to answer "was it collected, and by whom?".
 */
class ListFoundItemsAction
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = [], bool $stillHereOnly = false): Collection
    {
        $yearId = (int) ($filters['academic_year_id'] ?? 0)
            ?: (int) AcademicYear::query()->where('status', 'active')->value('id');

        if ($yearId === 0) {
            return collect();
        }

        $query = FoundItem::query()
            ->where('academic_year_id', $yearId)
            ->when($stillHereOnly, fn ($q) => $q->stillHere())
            ->when(
                ! $stillHereOnly && ($filters['status'] ?? null) !== null && $filters['status'] !== '',
                fn ($q) => $q->where('status', $filters['status'])
            );

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('location', 'like', '%'.$search.'%');
            });
        }

        // Newest first: the thing lost this morning is the thing being looked for.
        return $query->orderByDesc('found_at')->orderByDesc('id')->get()->map(function (FoundItem $item): array {
            return [
                'id' => (int) $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'location' => $item->location,
                'held_at' => $item->held_at,
                'found_at' => $item->found_at?->toDateString(),
                'status' => $item->status instanceof FoundItemStatus ? $item->status->value : (string) $item->status,
                'returned_at' => $item->returned_at?->toDateTimeString(),
                'returned_to' => $item->returned_to,
                'has_photo' => $item->photo_media_id !== null,
            ];
        });
    }

    /**
     * How many are still on the shelf — for a portal tile.
     */
    public function stillHereCount(): int
    {
        $yearId = (int) AcademicYear::query()->where('status', 'active')->value('id');

        if ($yearId === 0) {
            return 0;
        }

        return (int) DB::table('found_items')
            ->where('academic_year_id', $yearId)
            ->where('status', FoundItemStatus::Listed->value)
            ->count();
    }
}
