<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\Appraisal;
use App\Domains\HR\Models\AppraisalCycle;
use App\Domains\People\Actions\ListStaffProfilesAction;

class ListAppraisalsAction
{
    /**
     * @return array{cycles: list<array<string, mixed>>, rows: list<array<string, mixed>>}
     */
    public function execute(?int $staffProfileId = null): array
    {
        $staff = app(ListStaffProfilesAction::class)->execute()->keyBy('id');

        $cycles = AppraisalCycle::query()->orderByDesc('opens_at')->get()->map(fn (AppraisalCycle $cycle) => [
            'id' => $cycle->id,
            'name' => $cycle->name,
            'academic_year_id' => $cycle->academic_year_id,
            'opens_at' => $cycle->opens_at?->toDateString(),
            'closes_at' => $cycle->closes_at?->toDateString(),
            'status' => $cycle->status,
        ])->values()->all();

        $rows = Appraisal::query()
            ->with('cycle')
            ->when($staffProfileId, fn ($query) => $query->where('staff_profile_id', $staffProfileId))
            ->orderByDesc('id')
            ->get()
            ->map(function (Appraisal $row) use ($staff): array {
                $profile = $staff->get($row->staff_profile_id);

                return [
                    'id' => $row->id,
                    'cycle_id' => $row->cycle_id,
                    'cycle_name' => $row->cycle?->name,
                    'staff_profile_id' => $row->staff_profile_id,
                    'staff_name' => $profile ? trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) : '',
                    'strengths' => $row->strengths,
                    'development_areas' => $row->development_areas,
                    'status' => $row->status?->value ?? $row->status,
                    'acknowledged_at' => $row->acknowledged_at?->toDateTimeString(),
                    'staff_comment' => $row->staff_comment,
                ];
            })
            ->values()
            ->all();

        return ['cycles' => $cycles, 'rows' => $rows];
    }
}
