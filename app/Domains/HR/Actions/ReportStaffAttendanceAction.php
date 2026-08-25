<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\StaffAttendanceStatus;
use App\Domains\People\Actions\ListStaffProfilesAction;

class ReportStaffAttendanceAction
{
    /**
     * @param  array{from?: string|null, to?: string|null, department?: string|null, academic_year_id?: int|null}  $filters
     * @return array{late: list<array<string, mixed>>, absence: list<array<string, mixed>>}
     */
    public function execute(array $filters = []): array
    {
        $rows = app(ListStaffAttendanceAction::class)->execute($filters);
        $staff = app(ListStaffProfilesAction::class)
            ->execute(array_filter(['department' => $filters['department'] ?? null]))
            ->keyBy('id');

        $late = [];
        $absence = [];

        foreach ($staff as $profile) {
            $id = (int) $profile->id;
            $late[$id] = [
                'staff_profile_id' => $id,
                'staff_name' => trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')),
                'department' => $profile->department,
                'late_count' => 0,
                'minutes_late' => 0,
            ];
            $absence[$id] = [
                'staff_profile_id' => $id,
                'staff_name' => trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')),
                'department' => $profile->department,
                'absent_count' => 0,
                'half_day_count' => 0,
            ];
        }

        foreach ($rows as $row) {
            $id = (int) $row['staff_profile_id'];
            if ($row['status'] === StaffAttendanceStatus::Late->value && isset($late[$id])) {
                $late[$id]['late_count']++;
                $late[$id]['minutes_late'] += (int) ($row['minutes_late'] ?? 0);
            }
            if ($row['status'] === StaffAttendanceStatus::Absent->value && isset($absence[$id])) {
                $absence[$id]['absent_count']++;
            }
            if ($row['status'] === StaffAttendanceStatus::HalfDay->value && isset($absence[$id])) {
                $absence[$id]['half_day_count']++;
            }
        }

        return [
            'late' => array_values(array_filter($late, fn (array $row): bool => $row['late_count'] > 0)),
            'absence' => array_values(array_filter(
                $absence,
                fn (array $row): bool => $row['absent_count'] > 0 || $row['half_day_count'] > 0
            )),
        ];
    }
}
