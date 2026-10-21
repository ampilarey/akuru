<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\LibraryFollow;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\LibraryReadingProgress;
use App\Support\Said;

/**
 * B11 (LIBRARY_PLAN §41 "new content published, to readers", STATUS §5iu).
 *
 * The readers told are the ones who have already read or bought something
 * by the same writer — the people with a reason to hear it — and, since
 * BLOG_PLAN BL4, the writer's followers, by email too where they asked.
 * In-app, category `library`, so a reader who switched the category off hears
 * nothing. Once per item: `readers_notified_at` keeps a second publish (after
 * an unpublish, say) quiet. Capped, so a writer with thousands of readers
 * cannot make publishing slow.
 *
 * @return int how many readers were told
 */
class NotifyReadersOfNewWorkAction
{
    public const LIMIT = 500;

    public function execute(LibraryItem $item): int
    {
        if ($item->writer_id === null || $item->status?->value !== 'published' || $item->readers_notified_at !== null) {
            return 0;
        }
        $item->forceFill(['readers_notified_at' => now()])->save();

        $otherWorks = LibraryItem::query()
            ->where('writer_id', $item->writer_id)
            ->whereKeyNot($item->id)
            ->pluck('id');
        $followers = LibraryFollow::query()->where('writer_id', $item->writer_id)->pluck('notify_email', 'user_id');

        $readers = LibraryReadingProgress::query()->whereIn('library_item_id', $otherWorks)->pluck('user_id')
            ->merge(LibraryPurchase::query()->where('status', 'paid')->whereIn('library_item_id', $otherWorks)->pluck('user_id'))
            ->merge($followers->keys())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->reject(fn (int $id) => $id === (int) ($item->writer?->user_id ?? 0))
            ->take(self::LIMIT);

        $writer = $item->writer?->display_name ?? 'A writer you read';
        $notify = app(NotifyLibraryUserAction::class);
        foreach ($readers as $userId) {
            $title = Said::of('library_notices.new_work_title', ['writer' => $writer]);
            $message = Said::of('library_notices.on_shelf_body', ['title' => $item->title]);
            if ($followers[$userId] ?? false) {
                $notify->withEmail($userId, $title, $message, '/library/'.$item->slug);
            } else {
                $notify->execute($userId, $title, $message, '/library/'.$item->slug);
            }
        }

        return $readers->count();
    }
}
