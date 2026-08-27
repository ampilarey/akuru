<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryBookmark;
use App\Domains\Library\Models\LibraryReadingProgress;

/**
 * L2 reader dashboard data (LIBRARY_PLAN §10): continue reading +
 * bookmarks. Private to the reader (§43.8).
 */
class ListMyLibraryAction
{
    /**
     * @return array{continue: list<array<string, mixed>>, bookmarks: list<array<string, mixed>>}
     */
    public function execute(int $userId): array
    {
        $continue = LibraryReadingProgress::query()
            ->with('item')
            ->where('user_id', $userId)
            ->orderByDesc('last_read_at')
            ->limit(50)
            ->get()
            ->filter(fn (LibraryReadingProgress $row) => $row->item !== null && $row->item->status?->value === 'published')
            ->map(fn (LibraryReadingProgress $row): array => [
                'item_id' => (int) $row->library_item_id,
                'title' => $row->item->title,
                'slug' => $row->item->slug,
                'current_page' => (int) $row->current_page,
                'progress_percent' => (int) $row->progress_percent,
                'completed' => $row->completed_at !== null,
                'last_read_at' => $row->last_read_at?->toDateTimeString(),
            ])
            ->values()
            ->all();

        $bookmarks = LibraryBookmark::query()
            ->with('item')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->filter(fn (LibraryBookmark $row) => $row->item !== null)
            ->map(fn (LibraryBookmark $row): array => [
                'item_id' => (int) $row->library_item_id,
                'title' => $row->item->title,
                'slug' => $row->item->slug,
                'page_number' => (int) $row->page_number,
                'note' => $row->note,
            ])
            ->values()
            ->all();

        return ['continue' => $continue, 'bookmarks' => $bookmarks];
    }
}
