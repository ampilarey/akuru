<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryBookmark;

/**
 * A reader's private note on one page (LIBRARY_PLAN §9.1 "private notes",
 * §10 "Notes & Bookmarks").
 *
 * **Everything this needs already existed except a way to type one.**
 * `library_bookmarks.note` is a column, it is on the model's `$fillable`,
 * `ToggleLibraryBookmarkAction` has always accepted a `$note` argument, the
 * reader controller has always validated `note` at 500 characters, and
 * `ListMyLibraryAction` reads it back — `my.blade.php` even renders it when
 * present. The reader's bookmark form sent a page number and nothing else, so
 * the value was unreachable from end to end and My Library displayed a field
 * no reader could ever fill.
 *
 * **Why this is separate from the toggle.** `ToggleLibraryBookmarkAction`
 * deletes the row when one already exists, which is right for a button that
 * says "Remove bookmark" — and quietly wrong for saving a note, because typing
 * on an already-bookmarked page would delete the bookmark instead of keeping
 * the words. Two controls, two verbs: the button still toggles, and this saves.
 *
 * A note implies the bookmark, so writing one on an unbookmarked page creates
 * the row rather than refusing. An empty note clears the text and **keeps** the
 * bookmark: clearing what you wrote is not the same as un-bookmarking, and the
 * button next to it already does that.
 */
class SaveLibraryPageNoteAction
{
    public function execute(int $userId, int $itemId, int $page, ?string $note): LibraryBookmark
    {
        $note = trim((string) $note);

        $bookmark = LibraryBookmark::query()
            ->where('user_id', $userId)
            ->where('library_item_id', $itemId)
            ->where('page_number', $page)
            ->first();

        if ($bookmark === null) {
            return LibraryBookmark::query()->create([
                'user_id' => $userId,
                'library_item_id' => $itemId,
                'page_number' => $page,
                'note' => $note !== '' ? $note : null,
            ]);
        }

        $bookmark->note = $note !== '' ? $note : null;
        $bookmark->save();

        return $bookmark;
    }
}
