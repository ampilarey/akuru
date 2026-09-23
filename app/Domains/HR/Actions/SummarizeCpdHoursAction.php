<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\CpdRecord;
use App\Domains\People\Actions\ListStaffProfilesAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Hours of continuing professional development per staff member: this
 * academic year and all time, with the count of records behind each. S5.5
 * asked for a *"CPD hours per staff"* view; the CPD screen listed records
 * and left the adding-up to the reader (S5 audit D5, STATUS §5ff).
 *
 * "This year" is the current academic year's date span. The year comes off
 * the backbone table by query (rule 3: no Academics model in HR).
 */
class SummarizeCpdHoursAction
{
    /**
     * @return Collection<int, array{staff_profile_id: int, staff_name: string, hours_this_year: float, records_this_year: int, hours_total: float, records_total: int}>
     */
    public function execute(?int $staffProfileId = null): Collection
    {
        $year = DB::table('academic_years')->where('is_current', true)->orderByDesc('id')->first(['id', 'start_date', 'end_date']);

        $records = CpdRecord::query()
            ->when($staffProfileId, fn ($query) => $query->where('staff_profile_id', $staffProfileId))
            ->get(['staff_profile_id', 'hours', 'date'])
            ->groupBy('staff_profile_id');

        $staff = app(ListStaffProfilesAction::class)->execute(['status' => 'active'])
            ->when($staffProfileId, fn ($rows) => $rows->where('id', $staffProfileId));

        return $staff->map(function ($profile) use ($records, $year): array {
            $own = $records->get($profile->id, collect());
            $thisYear = $year === null ? collect() : $own->filter(function (CpdRecord $row) use ($year): bool {
                $date = $row->date?->toDateString();

                return $date !== null && $date >= (string) $year->start_date && $date <= (string) $year->end_date;
            });

            return [
                'staff_profile_id' => (int) $profile->id,
                'staff_name' => trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')),
                'hours_this_year' => round((float) $thisYear->sum('hours'), 1),
                'records_this_year' => $thisYear->count(),
                'hours_total' => round((float) $own->sum('hours'), 1),
                'records_total' => $own->count(),
            ];
        })->sortByDesc('hours_this_year')->values();
    }
}
