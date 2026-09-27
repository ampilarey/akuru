<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\DiscountRedemption;
use App\Domains\Commerce\Models\PromotionCampaign;
use App\Domains\Media\Actions\ResolvePublicMediaUrlAction;

/**
 * B4 (§18): campaigns as arrays, for whoever applies or administers them.
 * `active()` is the ones a reader may get right now; `execute()` is every
 * campaign with its performance — how many purchases used it and what
 * they were given — for the office. Commerce data only: what an item or
 * a writer is stays with the Library.
 */
class ListPromotionCampaignsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function active(): array
    {
        $now = now();

        return PromotionCampaign::query()
            ->with('targets')
            ->where('status', 'active')
            ->where('starts_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderBy('ends_at')
            ->orderBy('id')
            ->get()
            ->map(fn (PromotionCampaign $campaign) => $this->serialize($campaign))
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(): array
    {
        $campaigns = PromotionCampaign::query()->with('targets')->orderByDesc('starts_at')->orderByDesc('id')->get();
        $uses = DiscountRedemption::query()
            ->whereIn('promotion_campaign_id', $campaigns->pluck('id'))
            ->whereIn('status', ['pending', 'confirmed'])
            ->selectRaw('promotion_campaign_id, COUNT(*) as uses, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as confirmed, SUM(CASE WHEN status = ? THEN amount_discounted ELSE 0 END) as given', ['confirmed', 'confirmed'])
            ->groupBy('promotion_campaign_id')
            ->get()
            ->keyBy('promotion_campaign_id');

        return $campaigns->map(function (PromotionCampaign $campaign) use ($uses) {
            $row = $uses->get($campaign->id);

            return $this->serialize($campaign) + [
                'uses' => (int) ($row?->uses ?? 0),
                'confirmed' => (int) ($row?->confirmed ?? 0),
                'given' => round((float) ($row?->given ?? 0), 2),
            ];
        })->values()->all();
    }

    /**
     * B4b (§18 "buy 500, get 50"): the bonus a live gift-card campaign puts
     * on a card bought for `$amount` — nothing off the price, value added
     * to the card, funded by the Institute. Only campaigns that name
     * `gift_card` as a target, only from their minimum amount up, the
     * biggest bonus if several.
     *
     * @return array{campaign_id: int, name: string, slug: string, bonus: float}|null
     */
    public function giftCardBonus(float $amount): ?array
    {
        $best = null;
        foreach ($this->active() as $campaign) {
            if (! $campaign['is_gift_card_bonus']) {
                continue;
            }
            if ($campaign['minimum_amount'] !== null && $amount < (float) $campaign['minimum_amount']) {
                continue;
            }
            $bonus = self::amountOff($campaign, $amount);
            if ($bonus <= 0 || ($best !== null && $bonus <= $best['bonus'])) {
                continue;
            }
            $best = ['campaign_id' => (int) $campaign['id'], 'name' => $campaign['name'], 'slug' => $campaign['slug'], 'bonus' => $bonus];
        }

        return $best;
    }

    /**
     * What a campaign takes off a price: its percentage or fixed amount,
     * capped by its maximum and by the price itself.
     *
     * @param  array<string, mixed>  $campaign
     */
    public static function amountOff(array $campaign, float $price): float
    {
        $off = $campaign['discount_type'] === 'percentage'
            ? $price * ((float) $campaign['discount_value'] / 100)
            : (float) $campaign['discount_value'];
        if ($campaign['max_discount_amount'] !== null) {
            $off = min($off, (float) $campaign['max_discount_amount']);
        }

        return round(max(min($off, $price), 0), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PromotionCampaign $campaign): array
    {
        $now = now();
        $state = match (true) {
            $campaign->status === 'ended' => 'ended',
            $campaign->starts_at->isFuture() => 'scheduled',
            $campaign->ends_at !== null && $campaign->ends_at->lte($now) => 'expired',
            default => 'live',
        };

        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'slug' => $campaign->slug,
            'description' => $campaign->description,
            // B4c: the offer's picture, public media.
            'banner_url' => $campaign->banner_media_file_id
                ? app(ResolvePublicMediaUrlAction::class)->execute((int) $campaign->banner_media_file_id)
                : null,
            'starts_at' => $campaign->starts_at?->toDateTimeString(),
            'ends_at' => $campaign->ends_at?->toDateTimeString(),
            'ends_on' => $campaign->ends_at?->timezone(config('app.timezone'))->toDateString(),
            'discount_type' => $campaign->discount_type?->value,
            'discount_value' => (float) $campaign->discount_value,
            'max_discount_amount' => $campaign->max_discount_amount !== null ? (float) $campaign->max_discount_amount : null,
            'minimum_amount' => $campaign->minimum_amount !== null ? (float) $campaign->minimum_amount : null,
            'funding_source' => $campaign->funding_source,
            'status' => $campaign->status,
            'state' => $state,
            'targets' => $campaign->targets->map(fn ($target) => [
                'type' => $target->target_type,
                'id' => $target->target_id !== null ? (int) $target->target_id : null,
            ])->values()->all(),
            // B4b: a bonus on gift cards rather than a price off an item.
            'is_gift_card_bonus' => $campaign->targets->contains(fn ($target) => $target->target_type === 'gift_card'),
        ];
    }
}
