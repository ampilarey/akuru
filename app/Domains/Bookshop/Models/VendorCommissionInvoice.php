<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Akuru's monthly tax invoice to a vendor for its commission (BOOKSHOP_PLAN
 * §8, audit finding 4): a service Akuru sells, so the vendor's accountant
 * can book it; GST on it only when Akuru is registered. Issued once per
 * vendor and month; never edited after issue.
 */
class VendorCommissionInvoice extends Model
{
    protected $fillable = [
        'vendor_id', 'number', 'period_start', 'period_end', 'orders_count', 'sales', 'commission', 'tax_rate', 'tax',
        'total', 'currency', 'issuer_name', 'issuer_tin', 'vendor_legal_name', 'vendor_tin', 'issued_at', 'issued_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'sales' => 'decimal:2',
            'commission' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'issued_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
