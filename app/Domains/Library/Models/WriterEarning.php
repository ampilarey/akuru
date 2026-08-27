<?php

namespace App\Domains\Library\Models;

use Illuminate\Database\Eloquent\Model;

class WriterEarning extends Model
{
    protected $fillable = [
        'writer_id',
        'library_item_id',
        'library_purchase_id',
        'gross_amount',
        'discount_amount',
        'discount_funding_source',
        'wallet_amount',
        'bml_amount',
        'platform_commission',
        'writer_amount',
        'status',
        'available_at',
        'paid_at',
        'writer_payout_id',
    ];

    protected function casts(): array
    {
        return [
            'gross_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'wallet_amount' => 'decimal:2',
            'bml_amount' => 'decimal:2',
            'platform_commission' => 'decimal:2',
            'writer_amount' => 'decimal:2',
            'available_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
