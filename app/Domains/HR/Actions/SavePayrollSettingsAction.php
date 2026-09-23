<?php

namespace App\Domains\HR\Actions;

use App\Domains\Settings\Actions\SetSettingAction;
use Illuminate\Validation\ValidationException;

/**
 * The payroll rules and the payroll switch, set by the school.
 *
 * `payroll.rules` is what every payslip is computed from — pension rates,
 * working days, rounding, tax brackets (ADR-016) — and `payroll.enabled` is
 * the settings half of the kill-switch (`config('payroll.enabled')` AND the
 * row, `ResolvePayrollSettingsAction`). Both were read on every run and
 * editable from no screen (S5 audit D3, STATUS §5fe).
 *
 * Saving needs `payroll.approve`, the checker's permission, not the runner's:
 * a rule change moves every net figure the next run produces. The
 * environment flag is deliberately not settable here — that is the owner's
 * gate (S5 DoD line 64), and a screen that could flip it would defeat it.
 */
class SavePayrollSettingsAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): void
    {
        $rules = [
            'employee_pension_rate' => $this->rate($data['employee_pension_rate'] ?? null, 'employee_pension_rate'),
            'employer_pension_rate' => $this->rate($data['employer_pension_rate'] ?? null, 'employer_pension_rate'),
            'working_days' => $this->workingDays($data['working_days'] ?? null),
            'rounding' => 'half_up_2',
            'tax_brackets' => $this->brackets($data['tax_brackets'] ?? null),
        ];

        $set = app(SetSettingAction::class);
        $set->execute('payroll.rules', $rules, 'json', 'payroll', 'Payroll computation rules (ADR-016)');
        $set->execute('payroll.enabled', filter_var($data['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN), 'boolean', 'payroll', 'Payroll enabled (with PAYROLL_ENABLED)');
    }

    private function rate(mixed $value, string $field): float
    {
        if (! is_numeric($value) || (float) $value < 0 || (float) $value > 0.5) {
            throw ValidationException::withMessages([$field => 'A rate between 0 and 0.5 (0.07 is seven percent).']);
        }

        return round((float) $value, 4);
    }

    private function workingDays(mixed $value): int
    {
        if (! is_numeric($value) || (int) $value < 1 || (int) $value > 31) {
            throw ValidationException::withMessages(['working_days' => 'Between 1 and 31 working days a month.']);
        }

        return (int) $value;
    }

    /**
     * Ascending ceilings, the last open-ended, every rate between 0 and 1.
     *
     * @return list<array{up_to: float|null, rate: float}>
     */
    private function brackets(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            throw ValidationException::withMessages(['tax_brackets' => 'At least one bracket, the last with no ceiling.']);
        }

        $brackets = [];
        $previous = 0.0;
        $count = count($value);
        foreach (array_values($value) as $i => $row) {
            $rate = $row['rate'] ?? null;
            if (! is_numeric($rate) || (float) $rate < 0 || (float) $rate > 1) {
                throw ValidationException::withMessages(['tax_brackets' => 'Each rate between 0 and 1.']);
            }

            $upTo = $row['up_to'] ?? null;
            $last = $i === $count - 1;
            if ($last) {
                if ($upTo !== null && $upTo !== '') {
                    throw ValidationException::withMessages(['tax_brackets' => 'The last bracket has no ceiling — leave it blank.']);
                }
                $brackets[] = ['up_to' => null, 'rate' => round((float) $rate, 4)];

                continue;
            }

            if (! is_numeric($upTo) || (float) $upTo <= $previous) {
                throw ValidationException::withMessages(['tax_brackets' => 'Ceilings must rise from one bracket to the next, and only the last is open.']);
            }
            $previous = (float) $upTo;
            $brackets[] = ['up_to' => (float) $upTo, 'rate' => round((float) $rate, 4)];
        }

        return $brackets;
    }
}
