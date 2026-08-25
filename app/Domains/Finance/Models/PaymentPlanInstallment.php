<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\InstallmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentPlanInstallment extends Model
{
    protected $fillable = [
        'payment_plan_id',
        'sequence',
        'due_date',
        'amount',
        'paid_amount',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'status' => InstallmentStatus::class,
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class, 'payment_plan_id');
    }

    public function remaining(): float
    {
        return round((float) $this->amount - (float) $this->paid_amount, 2);
    }
}
