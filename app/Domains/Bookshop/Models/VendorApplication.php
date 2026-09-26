<?php

namespace App\Domains\Bookshop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone asking to open a shop in the Akuru Bookstore (BOOKSHOP_PLAN §3,
 * slice B9a: "public vendor onboarding, apply → approve, like writers").
 */
class VendorApplication extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const DECLINED = 'declined';

    protected $fillable = [
        'user_id', 'shop_name', 'legal_name', 'tin', 'contact_email', 'contact_phone', 'island', 'what_they_sell', 'link',
        'agreement_accepted_at', 'status', 'decided_by', 'decided_at', 'decision_note', 'vendor_id',
    ];

    protected function casts(): array
    {
        return ['agreement_accepted_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
