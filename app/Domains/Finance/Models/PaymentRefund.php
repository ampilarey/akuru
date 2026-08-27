<?php

namespace App\Domains\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P4.3: append-only refund record against a confirmed payment. Rows are
 * never updated or deleted (rule 12 ledger discipline).
 */
class PaymentRefund extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'payment_id',
        'amount',
        'currency',
        'destination',
        'reason',
        'refunded_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
