<?php

namespace App\Domains\Library\Actions;

use App\Domains\Commerce\Actions\ListPromotionCampaignsAction;
use App\Domains\Library\Models\LibraryItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * B4 (LIBRARY_PLAN §18): which live campaign, if any, covers an item, and
 * what the item costs under it. Commerce keeps campaigns and their targets
 * as strings and ids; this is where `library_category` means the item's
 * category and `writer_profile` its writer. Several campaigns covering one
 * item: the reader gets the biggest saving. Only paid items with a price
 * can be on offer.
 *
 * One instance loads the live campaigns once and answers for every item on
 * a shelf, so a listing costs one query, not one per card.
 */
class ResolveLibraryItemPromotionAction
{
    /** @var list<array<string, mixed>>|null */
    private ?array $campaigns = null;

    /**
     * @return array{campaign_id: int, name: string, slug: string, amount_off: float, price: float, ends_on: ?string}|null
     */
    public function execute(LibraryItem $item): ?array
    {
        if ($item->access_type?->value !== 'paid' || (float) $item->price <= 0) {
            return null;
        }
        $price = round((float) $item->price, 2);
        $best = null;
        foreach ($this->campaigns() as $campaign) {
            if (! $this->covers($campaign, $item)) {
                continue;
            }
            $off = ListPromotionCampaignsAction::amountOff($campaign, $price);
            if ($off <= 0 || ($best !== null && $off <= $best['amount_off'])) {
                continue;
            }
            $best = [
                'campaign_id' => (int) $campaign['id'],
                'name' => $campaign['name'],
                'slug' => $campaign['slug'],
                'amount_off' => $off,
                'price' => round($price - $off, 2),
                'ends_on' => $campaign['ends_on'],
            ];
        }

        return $best;
    }

    /**
     * Narrow an items query to what live campaigns cover — all of them, or
     * one by its slug. A campaign that covers nothing, or no live campaign
     * at all, matches nothing rather than everything.
     */
    public function constrain(Builder $query, ?string $slug = null): Builder
    {
        $campaigns = $slug === null
            ? $this->campaigns()
            : array_values(array_filter($this->campaigns(), fn ($campaign) => $campaign['slug'] === $slug));
        $query->where('library_items.access_type', 'paid')->where('library_items.price', '>', 0);
        if ($campaigns === []) {
            return $query->whereRaw('1 = 0');
        }

        $items = [];
        $categories = [];
        $writers = [];
        foreach ($campaigns as $campaign) {
            foreach ($campaign['targets'] as $target) {
                switch ($target['type']) {
                    case 'all':
                        return $query;
                    case 'library_item':
                        $items[] = (int) $target['id'];
                        break;
                    case 'library_category':
                        $categories[] = (int) $target['id'];
                        break;
                    case 'writer_profile':
                        $writers[] = (int) $target['id'];
                        break;
                }
            }
        }

        return $query->where(fn (Builder $sub) => $sub
            ->whereIn('library_items.id', $items ?: [0])
            ->orWhereIn('library_items.library_category_id', $categories ?: [0])
            ->orWhereIn('library_items.writer_id', $writers ?: [0]));
    }

    /**
     * @param  array<string, mixed>  $campaign
     */
    private function covers(array $campaign, LibraryItem $item): bool
    {
        foreach ($campaign['targets'] as $target) {
            $hit = match ($target['type']) {
                'all' => true,
                'library_item' => (int) $target['id'] === (int) $item->id,
                'library_category' => $item->library_category_id !== null && (int) $target['id'] === (int) $item->library_category_id,
                'writer_profile' => $item->writer_id !== null && (int) $target['id'] === (int) $item->writer_id,
                default => false,
            };
            if ($hit) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function campaigns(): array
    {
        return $this->campaigns ??= app(ListPromotionCampaignsAction::class)->active();
    }
}
