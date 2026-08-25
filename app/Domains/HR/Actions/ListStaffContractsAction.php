<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\StaffContract;
use App\Domains\People\Actions\ListStaffProfilesAction;
use Illuminate\Support\Collection;

class ListStaffContractsAction
{
    /**
     * @param  array{staff_profile_id?: int|null, status?: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $staff = app(ListStaffProfilesAction::class)->execute()->keyBy('id');

        $query = StaffContract::query()->orderByDesc('start_date');

        if (! empty($filters['staff_profile_id'])) {
            $query->where('staff_profile_id', (int) $filters['staff_profile_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(function (StaffContract $row) use ($staff): array {
            $profile = $staff->get($row->staff_profile_id);

            return [
                'id' => $row->id,
                'staff_profile_id' => $row->staff_profile_id,
                'staff_name' => $profile ? trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) : '',
                'contract_type' => $row->contract_type?->value ?? $row->contract_type,
                'start_date' => $row->start_date?->toDateString(),
                'end_date' => $row->end_date?->toDateString(),
                'probation_until' => $row->probation_until?->toDateString(),
                'basic_salary' => (string) $row->basic_salary,
                'allowances' => $row->allowances ?? [],
                'working_hours_per_week' => $row->working_hours_per_week,
                'document_id' => $row->document_id,
                'status' => $row->status?->value ?? $row->status,
            ];
        })->values();
    }
}
