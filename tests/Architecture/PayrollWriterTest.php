<?php

it('only payroll actions write payslips and payroll_periods', function () {
    $root = dirname(__DIR__, 2);
    $allowedPayslips = [
        $root.'/app/Domains/HR/Actions/RunPayrollAction.php',
        $root.'/app/Domains/HR/Actions/ApprovePayrollPeriodAction.php',
        $root.'/app/Domains/HR/Actions/MarkPayrollPaidAction.php',
        $root.'/database/migrations/2026_08_25_000026_s56_payroll.php',
    ];
    $allowedPeriods = [
        $root.'/app/Domains/HR/Actions/RunPayrollAction.php',
        $root.'/app/Domains/HR/Actions/ApprovePayrollPeriodAction.php',
        $root.'/app/Domains/HR/Actions/MarkPayrollPaidAction.php',
        $root.'/app/Domains/HR/Actions/LockPayrollPeriodAction.php',
        $root.'/database/migrations/2026_08_25_000026_s56_payroll.php',
    ];

    $payslipHits = [];
    $periodHits = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $contents = file_get_contents($path);

        if (! in_array($path, $allowedPayslips, true)) {
            if (preg_match('/DB::table\(\s*[\'"]payslips[\'"]\s*\)/', $contents)) {
                $payslipHits[] = $path;
            }
            if (preg_match('/Payslip::query\(\)->(create|insert|update|upsert|updateOrCreate)/', $contents)) {
                $payslipHits[] = $path;
            }
            if (preg_match('/Payslip::(create|insert|updateOrCreate)\(/', $contents)) {
                $payslipHits[] = $path;
            }
        }

        if (! in_array($path, $allowedPeriods, true)) {
            if (preg_match('/DB::table\(\s*[\'"]payroll_periods[\'"]\s*\)/', $contents)) {
                $periodHits[] = $path;
            }
            if (preg_match('/PayrollPeriod::query\(\)->(create|insert|update|upsert|updateOrCreate|firstOrCreate)/', $contents)) {
                $periodHits[] = $path;
            }
            if (preg_match('/PayrollPeriod::(create|insert|updateOrCreate|firstOrCreate)\(/', $contents)) {
                $periodHits[] = $path;
            }
        }
    }

    expect(array_values(array_unique($payslipHits)))->toBe([])
        ->and(array_values(array_unique($periodHits)))->toBe([]);
});
