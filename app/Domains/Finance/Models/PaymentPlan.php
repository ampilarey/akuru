<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\PaymentPlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentPlan extends Model
{
    protected $fillable = [
        'invoice_id',
        'total_amount',
        'status',
        'created_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'status' => PaymentPlanStatus::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentPlanInstallment::class)->orderBy('sequence');
    }
}
