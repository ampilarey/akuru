<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryBookmark;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemPage;

/**
 * L2 protected reader (LIBRARY_PLAN §9): one page per request, permission
 * checked on every request, dynamic watermark on the delivered content
 * (§43.12). Reading a page records progress for signed-in readers.
 * Returns null when the item does not exist/is not published; the
 * 'can_read' flag carries the gate outcome for the controller to act on.
 */
class PresentLibraryReaderAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $slug, int $page, ?int $userId, string $watermarkLabel): ?array
    {
        $item = LibraryItem::query()->where('slug', $slug)->where('status', 'published')->first();
        if ($item === null) {
            return null;
        }

        $gate = app(ResolveLibraryAccessAction::class)->execute($item, $userId);
        $canRead = $gate['can_read'];
        $previewPages = (int) ($gate['preview_pages'] ?? 0);
        $isPreview = ! $canRead && $previewPages > 0;

        $result = $gate + [
            'id' => $item->id,
            'title' => $item->title,
            'slug' => $item->slug,
            'access_type' => $item->access_type?->value,
            'is_preview' => $isPreview,
        ];
        if (! $canRead && ! $isPreview) {
            return $result;
        }

        $total = LibraryItemPage::query()->where('library_item_id', $item->id)->count();

        // §9.4. **The clamp lives here, on the server, and nowhere else.** A
        // previewer asking for page 900 of a 40-page allowance gets page 40,
        // not page 900 — the page number arrives from the query string, so a
        // cap enforced in the view or the controller would be no cap at all.
        $readable = $isPreview ? min($previewPages, $total) : $total;
        $page = max(1, min($page, max($readable, 1)));
        $content = LibraryItemPage::query()
            ->where('library_item_id', $item->id)
            ->where('page_number', $page)
            ->value('content');

        // Progress is a record of reading something you have; a sample is not
        // that. Writing it would put a book nobody bought into "continue
        // reading" and count its pages towards a completion the reader cannot
        // reach.
        if ($userId !== null && $total > 0 && ! $isPreview) {
            app(SaveReadingProgressAction::class)->execute($userId, $item->id, $page, $total);
        }

        return $result + [
            'page' => $page,
            'total_pages' => $total,
            // What the reader may actually open, which is the number the
            // navigation must count against — `total_pages` still reports the
            // real length, so a sample says "3 of 210" rather than "3 of 3".
            'readable_pages' => $readable,
            'content' => $content,
            'watermark' => $watermarkLabel.' • '.now()->format('Y-m-d H:i'),
            'bookmarked' => $userId !== null && LibraryBookmark::query()
                ->where('user_id', $userId)
                ->where('library_item_id', $item->id)
                ->where('page_number', $page)
                ->exists(),
        ];
    }
}
