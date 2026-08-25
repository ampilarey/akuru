<?php

namespace App\Domains\HR\Contracts;

interface PayrollCalculatorInterface
{
    /**
     * @param  array{
     *     basic_salary: float,
     *     allowances: list<array{name?: string, amount: float|int|string}>,
     *     unpaid_days: float,
     *     working_days: int,
     *     employee_pension_rate: float,
     *     employer_pension_rate: float,
     *     tax_brackets: list<array{up_to: float|int|null, rate: float}>,
     *     proration?: float
     * }  $input
     * @return array{
     *     basic_salary: float,
     *     allowances: list<array{name: string, amount: float}>,
     *     deductions: list<array{name: string, amount: float}>,
     *     gross: float,
     *     employee_pension: float,
     *     employer_pension: float,
     *     tax_withheld: float,
     *     unpaid_leave_deduction: float,
     *     net_pay: float
     * }
     */
    public function calculate(array $input): array;
}
