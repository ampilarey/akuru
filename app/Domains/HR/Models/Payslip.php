<?php

namespace App\Domains\HR\Models;

use App\Domains\HR\Enums\PayslipStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'staff_profile_id',
        'basic_salary',
        'allowances',
        'deductions',
        'gross',
        'employee_pension',
        'employer_pension',
        'tax_withheld',
        'unpaid_leave_deduction',
        'net_pay',
        'inputs',
        'document_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'allowances' => 'array',
            'deductions' => 'array',
            'inputs' => 'array',
            'basic_salary' => 'decimal:2',
            'gross' => 'decimal:2',
            'employee_pension' => 'decimal:2',
            'employer_pension' => 'decimal:2',
            'tax_withheld' => 'decimal:2',
            'unpaid_leave_deduction' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'status' => PayslipStatus::class,
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }
}
