<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\PayrollPeriodStatus;
use App\Domains\HR\Enums\PayslipStatus;
use App\Domains\HR\Models\PayrollPeriod;
use App\Domains\HR\Models\Payslip;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovePayrollPeriodAction
{
    public function execute(int $periodId, int $approvedBy): PayrollPeriod
    {
        app(ResolvePayrollSettingsAction::class)->assertEnabled();

        $period = PayrollPeriod::query()->findOrFail($periodId);

        if ($period->status !== PayrollPeriodStatus::Review) {
            throw ValidationException::withMessages(['payroll' => 'Only a period in review can be approved.']);
        }

        return DB::transaction(function () use ($period, $approvedBy): PayrollPeriod {
            Payslip::query()
                ->where('payroll_period_id', $period->id)
                ->where('status', PayslipStatus::Draft)
                ->update(['status' => PayslipStatus::Final]);

            $period->status = PayrollPeriodStatus::Approved;
            $period->approved_by = $approvedBy;
            $period->save();

            return $period->refresh();
        });
    }
}
