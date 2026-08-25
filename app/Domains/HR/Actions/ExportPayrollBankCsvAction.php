<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Enums\PayslipStatus;
use App\Domains\HR\Models\Payslip;
use App\Domains\People\Actions\ListStaffProfilesAction;

class ExportPayrollBankCsvAction
{
    public function execute(int $periodId): string
    {
        $staff = app(ListStaffProfilesAction::class)->execute()->keyBy('id');
        $rows = Payslip::query()
            ->where('payroll_period_id', $periodId)
            ->where('status', PayslipStatus::Final)
            ->orderBy('staff_profile_id')
            ->get();

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['staff_profile_id', 'staff_name', 'staff_number', 'net_pay']);
        foreach ($rows as $row) {
            $profile = $staff->get($row->staff_profile_id);
            fputcsv($out, [
                $row->staff_profile_id,
                $profile ? trim(($profile->first_name ?? '').' '.($profile->last_name ?? '')) : '',
                $profile?->staff_number ?? '',
                $row->net_pay,
            ]);
        }
        rewind($out);
        $csv = stream_get_contents($out) ?: '';
        fclose($out);

        return $csv;
    }
}
