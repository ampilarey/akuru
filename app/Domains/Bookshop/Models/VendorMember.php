<?php

namespace App\Domains\Bookshop\Models;

use App\Domains\Bookshop\Enums\VendorMemberRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's place in a vendor (BOOKSHOP_PLAN §3). The one definition of
 * "may act for this vendor" — `ResolveVendorScopeAction` reads it and
 * nothing else does.
 */
class VendorMember extends Model
{
    protected $fillable = [
        'vendor_id',
        'user_id',
        'role',
        'agreement_accepted_at',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'role' => VendorMemberRole::class,
            'agreement_accepted_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
