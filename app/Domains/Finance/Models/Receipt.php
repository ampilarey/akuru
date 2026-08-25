<?php

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Enums\ReceiptMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = [
        'invoice_id',
        'payment_id',
        'receipt_number',
        'amount',
        'method',
        'received_by',
        'received_at',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => ReceiptMethod::class,
            'received_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
