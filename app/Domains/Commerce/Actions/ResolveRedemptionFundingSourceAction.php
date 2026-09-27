<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\DiscountCode;
use App\Domains\Commerce\Models\DiscountRedemption;
use App\Domains\Commerce\Models\PromotionCampaign;

/**
 * L6 (§21): which pocket funded a purchase's discount — shared (default),
 * akuru, or writer — plus the discounted amount, so earnings can apply
 * the right commission model without other domains touching Commerce
 * models (rule 6).
 */
class ResolveRedemptionFundingSourceAction
{
    /**
     * @return array{amount_discounted: float, funding_source: string}|null
     */
    public function execute(string $purchaseType, int $purchaseId): ?array
    {
        $redemption = DiscountRedemption::query()
            ->where('purchase_type', $purchaseType)
            ->where('purchase_id', $purchaseId)
            ->whereIn('status', ['pending', 'confirmed'])
            ->orderByDesc('id')
            ->first();
        if ($redemption === null) {
            return null;
        }

        // B4: a campaign's redemption is funded by the campaign.
        $fundingSource = $redemption->promotion_campaign_id !== null
            ? PromotionCampaign::query()->whereKey($redemption->promotion_campaign_id)->value('funding_source')
            : DiscountCode::query()->whereKey($redemption->discount_code_id)->value('discount_funding_source');
        $fundingSource ??= 'shared';

        return [
            'amount_discounted' => (float) $redemption->amount_discounted,
            'funding_source' => (string) $fundingSource,
        ];
    }
}
