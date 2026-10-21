<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryBundle;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;

/**
 * LIBRARY_PLAN §19 (B4d): bundles for the office (every one, with its
 * sales) and for readers (on sale now, each work with its list price).
 */
class ListLibraryBundlesAction
{
    /** @return list<array<string, mixed>> */
    public function forOffice(): array
    {
        return LibraryBundle::query()->with('items:id,title,price')->orderByDesc('id')->get()->map(function (LibraryBundle $b) {
            $paid = LibraryPurchase::query()->where('library_bundle_id', $b->id)->where('status', 'paid');

            return [
                'id' => $b->id, 'slug' => $b->slug, 'title' => $b->title, 'title_dv' => $b->title_dv, 'title_ar' => $b->title_ar,
                'description' => $b->description, 'price' => (string) $b->price, 'currency' => $b->currency,
                'is_active' => $b->is_active, 'on_sale' => $b->onSale(), 'ends_at' => $b->ends_at?->toDateString(),
                'item_ids' => $b->items->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'items' => $b->items->map(fn (LibraryItem $i) => ['id' => $i->id, 'title' => $i->title, 'price' => (string) $i->price])->values()->all(),
                'list_total' => number_format((float) $b->items->sum(fn (LibraryItem $i) => (float) $i->price), 2, '.', ''),
                // One sale is one payment, or one wallet checkout (its purchases share a reader and a moment).
                'sold' => (int) (clone $paid)->selectRaw("count(distinct coalesce(cast(payment_id as char), concat('w', user_id, '-', created_at))) as n")->value('n'),
                'revenue' => number_format((float) (clone $paid)->sum('amount'), 2, '.', ''),
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function onSale(string $locale, ?int $userId = null): array
    {
        return LibraryBundle::query()->where('is_active', true)->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('id')->get()->map(fn (LibraryBundle $b) => $this->present($b, $locale, $userId))
            ->filter(fn (array $b) => count($b['items']) >= SaveLibraryBundleAction::MIN_ITEMS)->values()->all();
    }

    /** @return array<string, mixed> one bundle, as a reader sees it: what it holds, what it saves, what they have */
    public function present(LibraryBundle $bundle, string $locale, ?int $userId = null): array
    {
        $covers = app(ListLibraryItemsAction::class);
        $access = app(ResolveLibraryAccessAction::class);
        $items = $bundle->items()->where('status', 'published')->where('access_type', 'paid')->get()->map(fn (LibraryItem $i) => [
            'id' => $i->id, 'slug' => $i->slug, 'title' => $i->title, 'price' => (float) $i->price, 'cover_url' => $covers->coverUrl($i),
            'owned' => $userId !== null && $access->execute($i, $userId)['can_read'],
        ])->values()->all();
        $listTotal = round(array_sum(array_column($items, 'price')), 2);

        return [
            'id' => $bundle->id, 'slug' => $bundle->slug, 'title' => $bundle->titleIn($locale), 'description' => $bundle->description,
            'price' => (float) $bundle->price, 'currency' => $bundle->currency, 'ends_on' => $bundle->ends_at?->toDateString(),
            'items' => $items, 'list_total' => $listTotal, 'saving' => round(max(0, $listTotal - (float) $bundle->price), 2),
            'owned_count' => count(array_filter($items, fn (array $i) => $i['owned'])),
            'on_sale' => $bundle->onSale(),
        ];
    }
}
