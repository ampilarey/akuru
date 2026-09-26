<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\VendorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A shop selling inside the Akuru Online Bookshop (BOOKSHOP_PLAN §3). The
 * slug is fixed at creation — it is the storefront's address. `code` is the
 * three letters on its order numbers (audit finding 16).
 */
class Vendor extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'code',
        'tagline',
        'legal_name',
        'tin',
        'gst_registered',
        'status',
        'commission_rate',
        'contact_email',
        'contact_phone',
        'address',
        'opening_hours',
        'custom_host',
        'settings',
        'holiday_from',
        'holiday_until',
        'holiday_notice',
        'office_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => VendorStatus::class,
            'gst_registered' => 'boolean',
            'commission_rate' => 'decimal:2',
            'settings' => 'array',
            'holiday_from' => 'date',
            'holiday_until' => 'date',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(VendorMember::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
