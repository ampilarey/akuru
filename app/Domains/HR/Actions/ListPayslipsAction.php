<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Models\Payslip;
use App\Domains\People\Actions\ListStaffProfilesAction;
use Illuminate\Support\Collection;

class ListPayslipsAction
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(?int $periodId = null, ?int $staffProfileId = null): Collection
    {
        $staff = app(ListStaffProfilesAction::class)->execute()->keyBy('id');

        $rows = Payslip::query()
            ->with('period')
            ->when($periodId, fn ($query) => $query->where('payroll_period_id', $periodId))
            ->when($staffProfileId, fn ($query) => $query->where('staff_profile_id', $staffProfileId))
            ->orderBy('staff_profile_id')
            ->get();

        $previousNets = [];
        $first = $rows->first();
        if ($first?->period) {
            $previous = \App\Domains\HR\Models\PayrollPeriod::query()
                ->where(function ($query) use ($first): void {
                    $year = (int) $first->period->year;
                    $month = (int) $first->period->month;
                    if ($month === 1) {
                        $query->where('year', $year - 1)->where('month', 12);
                    } else {
                        $query->where('year', $year)->where('month', $month - 1);
                    }
                })
                ->first();
            if ($previous) {
                $previousNets = Payslip::query()
                    ->where('payroll_period_id', $previous->id)
                    ->pluck('net_pay', 'staff_profile_id')
                    ->all();
            }
        }

        return $rows->map(function (Payslip $row) use ($staff, $previousNets): array {
            $profile = $staff->get($row->staff_profile_id);

            return [
                'id' => $row->id,
                'payroll_period_id' => $row->payroll_period_id,
                'year' => $row->period?->year,
                'month' => $row->period?->month,
                'staff_profile_id' => $row->staff_profile_id,
                'staff_name' => $profile ? trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) : '',
                'basic_salary' => (string) $row->basic_salary,
                'gross' => (string) $row->gross,
                'employee_pension' => (string) $row->employee_pension,
                'employer_pension' => (string) $row->employer_pension,
                'tax_withheld' => (string) $row->tax_withheld,
                'unpaid_leave_deduction' => (string) $row->unpaid_leave_deduction,
                'net_pay' => (string) $row->net_pay,
                'status' => $row->status?->value ?? $row->status,
                'document_id' => $row->document_id,
                'previous_net' => isset($previousNets[$row->staff_profile_id])
                    ? (string) $previousNets[$row->staff_profile_id]
                    : null,
            ];
        })->values();
    }
}
