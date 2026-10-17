<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Actions\SyncLibraryItemPagesAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use Illuminate\Support\Facades\DB;

/**
 * BLOG_PLAN BL2c (D3): the office decides an edit to a published post. This
 * is the one place the live title and text of a published post change:
 *
 *  - approve: the waiting edit replaces the live text, and the reader's pages
 *    follow;
 *  - send back: the edit stays with the writer to fix and send again;
 *  - refuse: the edit is dropped.
 *
 * The post stays published throughout. Reached through the office's review
 * (ReviewLibraryItemSubmissionAction), which has already checked the edit is
 * the office's to decide.
 */
class DecidePostChangeAction
{
    public function execute(LibraryItem $item, int $reviewerUserId, string $decision, ?string $comment = null): LibraryItem
    {
        $comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;

        DB::transaction(function () use ($item, $reviewerUserId, $decision, $comment) {
            $drop = ['pending_title' => null, 'pending_body' => null, 'pending_saved_at' => null];
            $item->fill(match ($decision) {
                'approved' => ['title' => $item->pending_title ?: $item->title, 'body' => $item->pending_body, 'review_stage' => null] + $drop,
                'changes_requested' => ['review_stage' => null],
                default => ['review_stage' => null] + $drop,
            })->save();
            if ($decision === 'approved') {
                app(SyncLibraryItemPagesAction::class)->execute($item);
            }
            LibraryItemReview::query()->create([
                'library_item_id' => $item->id,
                'reviewer_user_id' => $reviewerUserId,
                'decision' => 'edit_'.TeacherReviewBlogPostAction::EDIT_WORDS[$decision],
                'comment' => $comment,
            ]);
        });

        if ($item->writer?->user_id) {
            app(NotifyLibraryUserAction::class)->execute(
                (int) $item->writer->user_id,
                match ($decision) {
                    'approved' => 'Your edit is live',
                    'changes_requested' => 'Changes asked for your edit',
                    default => 'Your edit was not accepted',
                },
                '"'.$item->title.'": '.($comment ?? ($decision === 'approved' ? 'readers now see the new text.' : 'the post stays as it was.')),
                '/my-blog',
                'post_decided',
            );
        }

        return $item->refresh();
    }
}
