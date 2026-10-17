<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryItemReview;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL2a (§6): the class teacher checks a student's post first.
 *
 *  - approve: it goes on to the office (`review_stage = office`); nothing is
 *    public yet;
 *  - changes: it comes back to the student, with the teacher's note;
 *  - refuse: it is refused, with the teacher's note.
 *
 * Only the teacher the post was sent to, and only while it waits for them.
 * Each decision is a row in the item's review trail (`teacher_…`).
 *
 * BL2c: an edit to a published post is checked the same way. The post stays
 * published with its live text whatever the teacher says: sending back keeps
 * the edit for the student to fix; refusing drops it.
 */
class TeacherReviewBlogPostAction
{
    public const DECISIONS = ['approved', 'changes_requested', 'rejected'];

    /** BL2c: how an edit's decision reads in the trail (`library_item_reviews.decision` holds 30 characters). */
    public const EDIT_WORDS = ['approved' => 'approved', 'changes_requested' => 'returned', 'rejected' => 'refused'];

    public function execute(int $itemId, int $teacherUserId, string $decision, ?string $comment = null): LibraryItem
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw ValidationException::withMessages(['decision' => __('blog.error_decision')]);
        }
        $comment = $comment !== null && trim($comment) !== '' ? trim($comment) : null;
        if ($decision !== 'approved' && $comment === null) {
            throw ValidationException::withMessages(['comment' => __('blog.error_teacher_note')]);
        }

        $item = LibraryItem::query()->findOrFail($itemId);
        $change = $item->status === LibraryItemStatus::Published && $item->hasPendingChange();
        if ((! $change && $item->status !== LibraryItemStatus::Submitted) || $item->review_stage !== 'teacher' || (int) $item->teacher_user_id !== $teacherUserId) {
            throw ValidationException::withMessages(['post' => __('blog.error_not_yours_to_check')]);
        }

        $item->fill($change ? match ($decision) {
            'approved' => ['review_stage' => 'office'],
            'changes_requested' => ['review_stage' => null],
            'rejected' => ['review_stage' => null, 'pending_title' => null, 'pending_body' => null, 'pending_saved_at' => null],
        } : match ($decision) {
            'approved' => ['review_stage' => 'office'],
            'changes_requested' => ['status' => LibraryItemStatus::ChangesRequested, 'review_stage' => null],
            'rejected' => ['status' => LibraryItemStatus::Rejected, 'review_stage' => null],
        })->save();
        LibraryItemReview::query()->create(['library_item_id' => $item->id, 'reviewer_user_id' => $teacherUserId, 'decision' => $change ? 'teacher_edit_'.self::EDIT_WORDS[$decision] : 'teacher_'.$decision, 'comment' => $comment]);

        $notify = app(NotifyLibraryUserAction::class);
        if ($decision === 'approved') {
            $notify->office('New blog post to check', '"'.$item->title.'" was checked by the class teacher and waits for the office.', '/admin/library');
        }
        if ($item->writer?->user_id) {
            $notify->execute(
                (int) $item->writer->user_id,
                match ($decision) {
                    'approved' => 'Your teacher checked your post',
                    'changes_requested' => 'Your teacher asked for changes',
                    'rejected' => 'Your post was not accepted',
                },
                '"'.$item->title.'": '.($comment ?? 'it goes on to the Library office.'),
                '/my-blog',
                'post_decided',
            );
        }

        return $item->refresh();
    }
}
