<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryReadingProgress;

/**
 * B11 (LIBRARY_PLAN §41 "continue-reading reminder", STATUS §5iu).
 *
 * A book opened, not finished, and untouched for a week or more gets one
 * in-app nudge with a link back to the page; not again for a fortnight,
 * and not at all once the book is a month cold — by then the nudge is a
 * nag. Category `library`, so it can be switched off. Runs from
 * `library:remind-readers` once a day.
 *
 * @return int how many reminders went
 */
class RemindReadersToContinueAction
{
    public const IDLE_DAYS = 7;

    public const STALE_DAYS = 30;

    public const REPEAT_DAYS = 14;

    public function execute(): int
    {
        $rows = LibraryReadingProgress::query()
            ->with('item')
            ->whereNull('completed_at')
            ->whereBetween('last_read_at', [now()->subDays(self::STALE_DAYS), now()->subDays(self::IDLE_DAYS)])
            ->where(fn ($query) => $query->whereNull('reminded_at')->orWhere('reminded_at', '<', now()->subDays(self::REPEAT_DAYS)))
            ->whereHas('item', fn ($query) => $query->where('status', 'published'))
            ->orderBy('id')
            ->limit(1000)
            ->get();

        $notify = app(NotifyLibraryUserAction::class);
        $sent = 0;
        foreach ($rows as $row) {
            $notify->execute(
                (int) $row->user_id,
                'Pick it back up?',
                'You were on page '.(int) $row->current_page.' of "'.$row->item->title.'".',
                '/library/'.$row->item->slug.'/read?page='.max(1, (int) $row->current_page),
            );
            $row->forceFill(['reminded_at' => now()])->save();
            $sent++;
        }

        return $sent;
    }
}
