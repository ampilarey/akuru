<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Contracts\PayrollCalculatorInterface;
use App\Domains\HR\Enums\PayrollPeriodStatus;
use App\Domains\HR\Enums\PayslipStatus;
use App\Domains\HR\Models\PayrollPeriod;
use App\Domains\HR\Models\Payslip;
use App\Domains\People\Actions\ListStaffProfilesAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RunPayrollAction
{
    public function __construct(private PayrollCalculatorInterface $calculator) {}

    public function execute(int $year, int $month, int $processedBy): PayrollPeriod
    {
        $settings = app(ResolvePayrollSettingsAction::class)->assertEnabled();

        $rules = $settings['rules'];
        $start = Carbon::create($year, $month, 1, 0, 0, 0, 'Indian/Maldives');
        $end = $start->copy()->endOfMonth();

        return DB::transaction(function () use ($year, $month, $processedBy, $rules, $start, $end): PayrollPeriod {
            $period = PayrollPeriod::query()->firstOrCreate(
                ['year' => $year, 'month' => $month],
                ['status' => PayrollPeriodStatus::Open, 'processed_by' => $processedBy],
            );

            if (in_array($period->status, [PayrollPeriodStatus::Paid, PayrollPeriodStatus::Locked], true)) {
                throw ValidationException::withMessages(['payroll' => 'This period is locked.']);
            }

            if ($period->status === PayrollPeriodStatus::Approved) {
                return $period;
            }

            $period->status = PayrollPeriodStatus::Processing;
            $period->processed_by = $processedBy;
            $period->save();

            $staff = app(ListStaffProfilesAction::class)->execute(['status' => 'active']);

            foreach ($staff as $profile) {
                $contract = app(ResolveActiveStaffContractAction::class)->execute((int) $profile->id);
                if ($contract === null) {
                    continue;
                }

                $existing = Payslip::query()
                    ->where('payroll_period_id', $period->id)
                    ->where('staff_profile_id', $profile->id)
                    ->first();

                if ($existing?->status === PayslipStatus::Final) {
                    continue;
                }

                $proration = $this->proration($contract, $start, $end);
                $unpaidDays = app(CountUnpaidLeaveDaysAction::class)->execute((int) $profile->id, $year, $month);
                $attendance = app(SummarizeStaffAttendanceMonthAction::class)->execute((int) $profile->id, $year, $month);

                $calculated = $this->calculator->calculate([
                    'basic_salary' => (float) $contract['basic_salary'],
                    'allowances' => $contract['allowances'] ?? [],
                    'unpaid_days' => $unpaidDays,
                    'working_days' => (int) ($rules['working_days'] ?? 22),
                    'employee_pension_rate' => (float) ($rules['employee_pension_rate'] ?? 0),
                    'employer_pension_rate' => (float) ($rules['employer_pension_rate'] ?? 0),
                    'tax_brackets' => $rules['tax_brackets'] ?? [],
                    'proration' => $proration,
                ]);

                $payload = [
                    'payroll_period_id' => $period->id,
                    'staff_profile_id' => $profile->id,
                    'basic_salary' => $calculated['basic_salary'],
                    'allowances' => $calculated['allowances'],
                    'deductions' => $calculated['deductions'],
                    'gross' => $calculated['gross'],
                    'employee_pension' => $calculated['employee_pension'],
                    'employer_pension' => $calculated['employer_pension'],
                    'tax_withheld' => $calculated['tax_withheld'],
                    'unpaid_leave_deduction' => $calculated['unpaid_leave_deduction'],
                    'net_pay' => $calculated['net_pay'],
                    'inputs' => [
                        'rules' => $rules,
                        'unpaid_days' => $unpaidDays,
                        'proration' => $proration,
                        'attendance' => $attendance,
                        'contract_id' => $contract['id'],
                    ],
                    'status' => PayslipStatus::Draft,
                ];

                if ($existing === null) {
                    Payslip::query()->create($payload);
                } else {
                    $existing->fill($payload);
                    $existing->save();
                }
            }

            $period->status = PayrollPeriodStatus::Review;
            $period->save();

            return $period->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $contract
     */
    private function proration(array $contract, Carbon $monthStart, Carbon $monthEnd): float
    {
        $start = Carbon::parse((string) $contract['start_date'], 'Indian/Maldives')->startOfDay();
        $end = ! empty($contract['end_date'])
            ? Carbon::parse((string) $contract['end_date'], 'Indian/Maldives')->startOfDay()
            : $monthEnd->copy()->startOfDay();

        if ($end->lt($monthStart) || $start->gt($monthEnd)) {
            return 0.0;
        }

        $from = $start->greaterThan($monthStart) ? $start : $monthStart->copy()->startOfDay();
        $to = $end->lessThan($monthEnd) ? $end : $monthEnd->copy()->startOfDay();
        $covered = $from->diffInDays($to) + 1;
        $daysInMonth = $monthStart->daysInMonth;

        return round($covered / $daysInMonth, 4);
    }
}
