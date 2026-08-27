<?php

namespace App\Domains\Commerce\Models;

use App\Domains\Commerce\Enums\DiscountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * §35.9. Discounts reduce price; they are never a payment method and never
 * apply to gift-card purchases (rule 12 / §43.15–16).
 */
class DiscountCode extends Model
{
    protected $fillable = [
        'code',
        'name',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'starts_at',
        'ends_at',
        'usage_limit',
        'per_user_limit',
        'minimum_order_amount',
        'applies_to_type',
        'discount_funding_source',
        'can_combine',
        'can_use_with_wallet',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'can_combine' => 'boolean',
            'can_use_with_wallet' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }
}
