<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The owner asks for the matured balance; the office pays it by bank transfer and records the reference, or declines with a note. */
class VendorPayout extends Model
{
    protected $fillable = [
        'vendor_id', 'amount', 'currency', 'status', 'requested_by', 'requested_at', 'decided_by', 'decided_at',
        'reference', 'note', 'bank_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PayoutStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
            'bank_snapshot' => 'array',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /** The earnings waiting on this payout while it is requested. */
    public function openEarnings(): HasMany
    {
        return $this->hasMany(VendorEarning::class, 'open_payout_id');
    }

    /** The earnings this payout paid. */
    public function paidEarnings(): HasMany
    {
        return $this->hasMany(VendorEarning::class, 'last_payout_id');
    }
}
