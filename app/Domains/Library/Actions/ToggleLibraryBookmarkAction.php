<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryBookmark;

class ToggleLibraryBookmarkAction
{
    /**
     * @return array{bookmarked: bool}
     */
    public function execute(int $userId, int $itemId, int $page, ?string $note = null): array
    {
        $existing = LibraryBookmark::query()
            ->where('user_id', $userId)
            ->where('library_item_id', $itemId)
            ->where('page_number', $page)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return ['bookmarked' => false];
        }

        LibraryBookmark::query()->create([
            'user_id' => $userId,
            'library_item_id' => $itemId,
            'page_number' => $page,
            'note' => $note,
        ]);

        return ['bookmarked' => true];
    }
}
