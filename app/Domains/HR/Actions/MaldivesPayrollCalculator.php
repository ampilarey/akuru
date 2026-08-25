<?php

namespace App\Domains\HR\Actions;

use App\Domains\HR\Contracts\PayrollCalculatorInterface;

class MaldivesPayrollCalculator implements PayrollCalculatorInterface
{
    public function calculate(array $input): array
    {
        $proration = isset($input['proration']) ? (float) $input['proration'] : 1.0;
        $proration = max(0, min(1, $proration));

        $basic = $this->round((float) $input['basic_salary'] * $proration);
        $allowances = [];
        $allowanceTotal = 0.0;
        foreach ($input['allowances'] ?? [] as $allowance) {
            $amount = $this->round((float) ($allowance['amount'] ?? 0) * $proration);
            $allowances[] = [
                'name' => (string) ($allowance['name'] ?? 'Allowance'),
                'amount' => $amount,
            ];
            $allowanceTotal += $amount;
        }

        $gross = $this->round($basic + $allowanceTotal);
        $workingDays = max(1, (int) ($input['working_days'] ?? 22));
        $unpaidDays = max(0, (float) ($input['unpaid_days'] ?? 0));
        $unpaid = $this->round(($basic / $workingDays) * $unpaidDays);

        $employeeRate = (float) ($input['employee_pension_rate'] ?? 0);
        $employerRate = (float) ($input['employer_pension_rate'] ?? 0);
        $employeePension = $this->round($gross * $employeeRate);
        $employerPension = $this->round($gross * $employerRate);

        $taxable = max(0, $this->round($gross - $unpaid));
        $tax = $this->tax($taxable, $input['tax_brackets'] ?? []);

        $net = $this->round($gross - $employeePension - $tax - $unpaid);

        return [
            'basic_salary' => $basic,
            'allowances' => $allowances,
            'deductions' => array_values(array_filter([
                $unpaid > 0 ? ['name' => 'Unpaid leave', 'amount' => $unpaid] : null,
                $employeePension > 0 ? ['name' => 'Employee pension', 'amount' => $employeePension] : null,
                $tax > 0 ? ['name' => 'Tax withheld', 'amount' => $tax] : null,
            ])),
            'gross' => $gross,
            'employee_pension' => $employeePension,
            'employer_pension' => $employerPension,
            'tax_withheld' => $tax,
            'unpaid_leave_deduction' => $unpaid,
            'net_pay' => $net,
        ];
    }

    /**
     * @param  list<array{up_to: float|int|null, rate: float}>  $brackets
     */
    private function tax(float $taxable, array $brackets): float
    {
        $tax = 0.0;
        $remaining = $taxable;
        $previous = 0.0;

        foreach ($brackets as $bracket) {
            $upTo = $bracket['up_to'] === null ? null : (float) $bracket['up_to'];
            $rate = (float) $bracket['rate'];
            $slice = $upTo === null ? $remaining : min($remaining, max(0, $upTo - $previous));
            if ($slice > 0 && $rate > 0) {
                $tax += $slice * $rate;
            }
            $remaining -= $slice;
            $previous = $upTo ?? $previous;
            if ($remaining <= 0) {
                break;
            }
        }

        return $this->round($tax);
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
