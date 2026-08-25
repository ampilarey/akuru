<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\CpdRecord;
use App\Domains\People\Actions\ListStaffProfilesAction;
use Illuminate\Support\Collection;

class ListCpdRecordsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $staffProfileId = null): Collection
    {
        $staff = app(ListStaffProfilesAction::class)->execute()->keyBy('id');

        return CpdRecord::query()
            ->when($staffProfileId, fn ($query) => $query->where('staff_profile_id', $staffProfileId))
            ->orderByDesc('date')
            ->get()
            ->map(function (CpdRecord $row) use ($staff): array {
                $profile = $staff->get($row->staff_profile_id);

                return [
                    'id' => $row->id,
                    'staff_profile_id' => $row->staff_profile_id,
                    'staff_name' => $profile ? trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) : '',
                    'title' => $row->title,
                    'provider' => $row->provider,
                    'hours' => (float) $row->hours,
                    'date' => $row->date?->toDateString(),
                ];
            })
            ->values();
    }
}
