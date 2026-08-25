<?php

namespace App\Domains\HR\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResolvePayrollSettingsAction
{
    /**
     * @return array{enabled: bool, rules: array<string, mixed>}
     */
    public function execute(): array
    {
        $rows = DB::table('settings')
            ->whereIn('key', ['payroll.enabled', 'payroll.rules'])
            ->pluck('value', 'key');

        $rules = json_decode((string) ($rows['payroll.rules'] ?? ''), true);
        if (! is_array($rules)) {
            $rules = [
                'employee_pension_rate' => 0.07,
                'employer_pension_rate' => 0.07,
                'working_days' => 22,
                'rounding' => 'half_up_2',
                'tax_brackets' => [
                    ['up_to' => 60000, 'rate' => 0],
                    ['up_to' => 100000, 'rate' => 0.08],
                    ['up_to' => null, 'rate' => 0.15],
                ],
            ];
        }

        return [
            'enabled' => (bool) config('payroll.enabled')
                && filter_var($rows['payroll.enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'rules' => $rules,
        ];
    }

    /**
     * @return array{enabled: bool, rules: array<string, mixed>}
     */
    public function assertEnabled(): array
    {
        $settings = $this->execute();
        if (! $settings['enabled']) {
            throw ValidationException::withMessages(['payroll' => 'Payroll is disabled.']);
        }

        return $settings;
    }
}
