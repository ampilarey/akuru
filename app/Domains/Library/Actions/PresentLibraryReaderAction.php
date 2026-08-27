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

        $access = $item->access_type?->value;
        $canRead = match ($access) {
            'free_public' => true,
            'free_login' => $userId !== null,
            default => false, // paid / course / manual — grants arrive in L3
        };

        $result = [
            'id' => $item->id,
            'title' => $item->title,
            'slug' => $item->slug,
            'access_type' => $access,
            'can_read' => $canRead,
            'requires_login' => $access === 'free_login' && $userId === null,
        ];
        if (! $canRead) {
            return $result;
        }

        $total = LibraryItemPage::query()->where('library_item_id', $item->id)->count();
        $page = max(1, min($page, max($total, 1)));
        $content = LibraryItemPage::query()
            ->where('library_item_id', $item->id)
            ->where('page_number', $page)
            ->value('content');

        if ($userId !== null && $total > 0) {
            app(SaveReadingProgressAction::class)->execute($userId, $item->id, $page, $total);
        }

        return $result + [
            'page' => $page,
            'total_pages' => $total,
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
