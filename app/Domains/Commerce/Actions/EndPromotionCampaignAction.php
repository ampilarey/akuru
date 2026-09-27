<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\PromotionCampaign;
use Illuminate\Validation\ValidationException;

/**
 * B4: the office stops a campaign early. The row stays — its redemptions
 * point at it — and its end is moved to now, so a reader mid-checkout
 * pays the price the page showed and nobody after them gets the offer.
 */
class EndPromotionCampaignAction
{
    public function execute(int $campaignId): PromotionCampaign
    {
        $campaign = PromotionCampaign::query()->whereKey($campaignId)->firstOrFail();
        if ($campaign->status === 'ended') {
            throw ValidationException::withMessages(['campaign' => 'This campaign has already ended.']);
        }

        $campaign->fill([
            'status' => 'ended',
            'ends_at' => $campaign->ends_at !== null && $campaign->ends_at->isPast() ? $campaign->ends_at : now(),
        ])->save();

        return $campaign->refresh();
    }
}
