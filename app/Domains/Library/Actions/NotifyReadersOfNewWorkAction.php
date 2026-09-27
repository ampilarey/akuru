<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\LibraryReadingProgress;

/**
 * B11 (LIBRARY_PLAN §41 "new content published, to readers", STATUS §5iu).
 *
 * There is no "follow a writer" yet, so the readers told are the ones who
 * have already read or bought something by the same writer — the people
 * with a reason to hear it. In-app, category `library`, so a reader who
 * switched the category off hears nothing. Capped, so a writer with
 * thousands of readers cannot make publishing slow.
 *
 * @return int how many readers were told
 */
class NotifyReadersOfNewWorkAction
{
    public const LIMIT = 500;

    public function execute(LibraryItem $item): int
    {
        if ($item->writer_id === null || $item->status?->value !== 'published') {
            return 0;
        }

        $otherWorks = LibraryItem::query()
            ->where('writer_id', $item->writer_id)
            ->whereKeyNot($item->id)
            ->pluck('id');
        if ($otherWorks->isEmpty()) {
            return 0;
        }

        $readers = LibraryReadingProgress::query()->whereIn('library_item_id', $otherWorks)->pluck('user_id')
            ->merge(LibraryPurchase::query()->where('status', 'paid')->whereIn('library_item_id', $otherWorks)->pluck('user_id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === (int) ($item->writer?->user_id ?? 0))
            ->take(self::LIMIT);

        $writer = $item->writer?->display_name ?? 'A writer you read';
        $notify = app(NotifyLibraryUserAction::class);
        foreach ($readers as $userId) {
            $notify->execute($userId, 'New from '.$writer, '"'.$item->title.'" is now on the library shelf.', '/library/'.$item->slug);
        }

        return $readers->count();
    }
}
