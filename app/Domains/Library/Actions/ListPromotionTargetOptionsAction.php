<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterProfile;

/**
 * B4: what the office may point a campaign at — the active categories,
 * the active writers, and the published paid items — as id/label pairs
 * for the picker and for naming a campaign's targets in its list.
 *
 * @return array{categories: list<array{id: int, label: string}>, writers: list<array{id: int, label: string}>, items: list<array{id: int, label: string}>}
 */
class ListPromotionTargetOptionsAction
{
    public function execute(): array
    {
        return [
            'categories' => array_map(
                fn (array $category) => ['id' => (int) $category['id'], 'label' => (string) $category['name']],
                app(ListLibraryCategoriesAction::class)->execute(),
            ),
            'writers' => WriterProfile::query()
                ->where('status', 'active')
                ->orderBy('display_name')
                ->get(['id', 'display_name'])
                ->map(fn (WriterProfile $writer) => ['id' => (int) $writer->id, 'label' => (string) $writer->display_name])
                ->values()
                ->all(),
            'items' => LibraryItem::query()
                ->where('status', 'published')
                ->where('access_type', 'paid')
                ->where('price', '>', 0)
                ->orderBy('title')
                ->get(['id', 'title', 'price'])
                ->map(fn (LibraryItem $item) => ['id' => (int) $item->id, 'label' => $item->title.' (MVR '.number_format((float) $item->price, 2).')'])
                ->values()
                ->all(),
        ];
    }
}
