<?php

namespace App\Domains\Commerce\Models;

use App\Domains\Commerce\Enums\DiscountType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * §35.10 (B4). A campaign is a discount with a window and a name; what it
 * covers is its targets. Like a code, it reduces the price and is never a
 * payment method (rule 12).
 */
class PromotionCampaign extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'banner_media_file_id',
        'starts_at',
        'ends_at',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'minimum_amount',
        'funding_source',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(PromotionCampaignTarget::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }
}
