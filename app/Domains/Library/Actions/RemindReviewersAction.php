<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReviewAssignment;

/**
 * RESEARCH_ARTICLES_PLAN R3b: a reviewer with an open report hears about it
 * twice — three days before it is due, and on the day — and each reminder
 * goes once. `reminded_at` records the last one sent:
 *
 *  - "due soon": the due date is three days away or less, and nothing has
 *    been sent;
 *  - "due today": the due date has arrived, and nothing has been sent since
 *    that day began.
 *
 * Only open assignments on items still with the reviewers; a new round sets
 * a new due date and clears `reminded_at`.
 */
class RemindReviewersAction
{
    public const DAYS_BEFORE = 3;

    /** @return int reminders sent */
    public function execute(): int
    {
        $now = now();
        $sent = 0;
        $notify = app(NotifyLibraryUserAction::class);

        $open = LibraryReviewAssignment::query()
            ->where('status', 'assigned')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now->copy()->addDays(self::DAYS_BEFORE)->endOfDay())
            ->get();
        $items = LibraryItem::query()->whereIn('id', $open->pluck('library_item_id'))->get()->keyBy('id');

        foreach ($open as $assignment) {
            $item = $items->get($assignment->library_item_id);
            if ($item === null || $item->status !== LibraryItemStatus::Submitted) {
                continue;
            }
            $dueDay = $assignment->due_at->copy()->timezone('Indian/Maldives')->startOfDay();
            $today = $now->copy()->timezone('Indian/Maldives');

            if ($today->greaterThanOrEqualTo($dueDay)) {
                if ($assignment->reminded_at !== null && $assignment->reminded_at->greaterThanOrEqualTo($dueDay)) {
                    continue;
                }
                $title = 'Peer review due today';
                $message = 'Your review of "'.$item->title.'" is due today.';
            } elseif ($assignment->reminded_at === null) {
                $title = 'Peer review due soon';
                $message = 'Your review of "'.$item->title.'" is due on '.$dueDay->toDateString().'.';
            } else {
                continue;
            }

            $notify->execute((int) $assignment->reviewer_user_id, $title, $message, '/review');
            $assignment->forceFill(['reminded_at' => $now])->save();
            $sent++;
        }

        return $sent;
    }
}
