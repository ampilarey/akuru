<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Enums\InstallmentStatus;
use App\Domains\Finance\Enums\PaymentPlanStatus;
use App\Domains\Finance\Models\PaymentPlan;
use App\Domains\Finance\Models\PaymentPlanInstallment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MarkDefaultedPaymentPlansAction
{
    public function execute(?string $asOf = null): int
    {
        $today = $asOf ?? now('Indian/Maldives')->toDateString();
        $days = (int) (DB::table('settings')->where('key', 'finance.plan_default_days')->value('value') ?? 14);
        $cutoff = Carbon::parse($today, 'Indian/Maldives')->subDays(max(0, $days))->toDateString();

        PaymentPlanInstallment::query()
            ->whereIn('status', [InstallmentStatus::Pending->value, InstallmentStatus::Partial->value])
            ->whereDate('due_date', '<', $today)
            ->update(['status' => InstallmentStatus::Overdue->value]);

        $planIds = PaymentPlanInstallment::query()
            ->where('status', InstallmentStatus::Overdue->value)
            ->whereDate('due_date', '<=', $cutoff)
            ->pluck('payment_plan_id');

        return PaymentPlan::query()
            ->whereIn('id', $planIds)
            ->where('status', PaymentPlanStatus::Active->value)
            ->update(['status' => PaymentPlanStatus::Defaulted->value]);
    }
}
