<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\StaffOnboardingItem;
use App\Domains\People\Actions\ListStaffProfilesAction;
use Illuminate\Support\Collection;

class ListStaffChecklistAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $staffProfileId = null, ?string $kind = null): Collection
    {
        $staff = app(ListStaffProfilesAction::class)->execute()->keyBy('id');

        return StaffOnboardingItem::query()
            ->when($staffProfileId, fn ($query) => $query->where('staff_profile_id', $staffProfileId))
            ->when($kind, fn ($query) => $query->where('kind', $kind))
            ->orderBy('staff_profile_id')
            ->orderBy('id')
            ->get()
            ->map(function (StaffOnboardingItem $row) use ($staff): array {
                $profile = $staff->get($row->staff_profile_id);

                return [
                    'id' => $row->id,
                    'staff_profile_id' => $row->staff_profile_id,
                    'staff_name' => $profile ? trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) : '',
                    'kind' => $row->kind?->value ?? $row->kind,
                    'item' => $row->item,
                    'done' => $row->done,
                    'done_at' => $row->done_at?->toDateTimeString(),
                ];
            })
            ->values();
    }
}
