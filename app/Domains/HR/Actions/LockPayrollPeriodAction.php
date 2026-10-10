<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\PayrollPeriodStatus;
use App\Domains\HR\Models\PayrollPeriod;
use Illuminate\Validation\ValidationException;

class LockPayrollPeriodAction
{
    public function execute(int $periodId): PayrollPeriod
    {
        app(ResolvePayrollSettingsAction::class)->assertEnabled();

        $period = PayrollPeriod::query()->findOrFail($periodId);

        if (! in_array($period->status, [PayrollPeriodStatus::Approved, PayrollPeriodStatus::Paid], true)) {
            throw ValidationException::withMessages(['payroll' => __('hr.error_lock_after_approval')]);
        }

        $period->status = PayrollPeriodStatus::Locked;
        $period->save();

        return $period->refresh();
    }
}
