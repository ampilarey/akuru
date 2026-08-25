<?php

namespace App\Domains\HR\Models;

use App\Domains\HR\Enums\PayrollPeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'year',
        'month',
        'status',
        'processed_by',
        'approved_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollPeriodStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function isLockedForEdits(): bool
    {
        return in_array($this->status, [
            PayrollPeriodStatus::Approved,
            PayrollPeriodStatus::Paid,
            PayrollPeriodStatus::Locked,
        ], true);
    }
}
