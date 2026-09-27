<?php

namespace App\Domains\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a campaign covers: `all`, or one `library_item`, `library_category`
 * or `writer_profile` by id (morph-map aliases, ADR-005). Commerce stores
 * the strings; the Library decides what they cover.
 */
class PromotionCampaignTarget extends Model
{
    protected $fillable = ['promotion_campaign_id', 'target_type', 'target_id'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromotionCampaign::class, 'promotion_campaign_id');
    }
}
