<?php

namespace App\Domains\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountRedemption extends Model
{
    protected $fillable = [
        'discount_code_id',
        // B4: a campaign's use is a redemption too; exactly one of the two is set.
        'promotion_campaign_id',
        'user_id',
        'purchase_type',
        'purchase_id',
        'amount_discounted',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount_discounted' => 'decimal:2',
        ];
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class, 'discount_code_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromotionCampaign::class, 'promotion_campaign_id');
    }
}
