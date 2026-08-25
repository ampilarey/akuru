<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\StaffAttendance;
use App\Domains\People\Actions\ListStaffProfilesAction;
use Illuminate\Support\Collection;

class ListStaffAttendanceAction
{
    /**
     * @param  array{academic_year_id?: int|null, date?: string|null, from?: string|null, to?: string|null, staff_profile_id?: int|null, department?: string|null, status?: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(array $filters = []): Collection
    {
        $staff = app(ListStaffProfilesAction::class)
            ->execute(array_filter(['department' => $filters['department'] ?? null]))
            ->keyBy('id');

        $query = StaffAttendance::query()->orderBy('date')->orderBy('staff_profile_id');

        if (! empty($filters['academic_year_id'])) {
            $query->where('academic_year_id', (int) $filters['academic_year_id']);
        }
        if (! empty($filters['date'])) {
            $query->whereDate('date', $filters['date']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('date', '<=', $filters['to']);
        }
        if (! empty($filters['staff_profile_id'])) {
            $query->where('staff_profile_id', (int) $filters['staff_profile_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['department'])) {
            $query->whereIn('staff_profile_id', $staff->keys()->all());
        }

        return $query->get()->map(function (StaffAttendance $row) use ($staff): array {
            $profile = $staff->get($row->staff_profile_id);

            return [
                'id' => $row->id,
                'staff_profile_id' => $row->staff_profile_id,
                'staff_name' => $profile ? trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) : '',
                'staff_number' => $profile->staff_number ?? null,
                'department' => $profile->department ?? null,
                'designation' => $profile->designation ?? null,
                'academic_year_id' => $row->academic_year_id,
                'date' => $row->date?->toDateString(),
                'check_in' => $row->check_in,
                'check_out' => $row->check_out,
                'status' => $row->status?->value ?? $row->status,
                'source' => $row->source?->value ?? $row->source,
                'minutes_late' => $row->minutes_late,
                'marked_by' => $row->marked_by,
                'remarks' => $row->remarks,
            ];
        })->values();
    }
}
