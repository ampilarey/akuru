<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemPage;

/**
 * L2: the body becomes reader pages, split on an explicit
 * <!-- pagebreak --> marker the admin places (LIBRARY_PLAN §36's
 * "secure HTML" path — PDF-to-page-image conversion needs server tooling
 * and is recorded as a later infrastructure step). No markers = one page.
 * Replaces the page set idempotently and keeps page_count honest.
 */
class SyncLibraryItemPagesAction
{
    public const PAGE_BREAK = '<!-- pagebreak -->';

    public function execute(LibraryItem $item): int
    {
        $body = (string) ($item->body ?? '');
        $chunks = $body === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(self::PAGE_BREAK, $body)), fn ($chunk) => $chunk !== ''));

        LibraryItemPage::query()->where('library_item_id', $item->id)->delete();
        foreach ($chunks as $index => $chunk) {
            LibraryItemPage::query()->create([
                'library_item_id' => $item->id,
                'page_number' => $index + 1,
                'content' => $chunk,
            ]);
        }

        $item->page_count = count($chunks) ?: null;
        $item->save();

        return count($chunks);
    }
}
