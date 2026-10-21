<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Models\LibraryComment;
use App\Support\Said;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL5 (§6): the office decides a comment — publish it, refuse it
 * with a note the commenter reads, or hide a published one at once. A
 * reported comment is off the page until decided here.
 *
 * A reader reports a published comment through `report()`: it leaves the
 * page and goes back to the office's list.
 */
class ModerateCommentAction
{
    public const DECISIONS = ['publish', 'refuse', 'hide'];

    public function execute(int $commentId, int $officeUserId, string $decision, ?string $note = null): LibraryComment
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw ValidationException::withMessages(['decision' => __('blog.error_decision')]);
        }
        $note = $note !== null && trim($note) !== '' ? trim($note) : null;
        if ($decision === 'refuse' && $note === null) {
            throw ValidationException::withMessages(['note' => __('blog.error_note_required')]);
        }
        $comment = LibraryComment::query()->with('item')->findOrFail($commentId);
        $comment->fill([
            'status' => ['publish' => 'published', 'refuse' => 'refused', 'hide' => 'hidden'][$decision],
            'moderated_by' => $officeUserId, 'moderated_at' => now(), 'decision_note' => $note,
        ])->save();

        if ($decision !== 'hide') {
            app(NotifyLibraryUserAction::class)->execute(
                (int) $comment->user_id,
                Said::of('library_notices.'.($decision === 'publish' ? 'comment_published_title' : 'comment_refused_title')),
                Said::of('library_notices.about_item', ['title' => (string) $comment->item?->title, 'comment' => $note ?? Said::of('library_notices.comment_published_default')]),
                '/library/'.$comment->item?->slug,
            );
        }

        return $comment->refresh();
    }

    public function report(int $userId, int $commentId): LibraryComment
    {
        $comment = LibraryComment::query()->whereKey($commentId)->where('status', 'published')->first();
        if ($comment === null) {
            throw ValidationException::withMessages(['comment' => __('blog.error_comment_not_reportable')]);
        }
        $comment->fill(['status' => 'reported', 'report_count' => $comment->report_count + 1])->save();
        app(NotifyLibraryUserAction::class)->office(Said::of('library_notices.comment_reported_title'), Said::of('library_notices.comment_reported_body'), '/admin/library/comments?status=reported');

        return $comment;
    }
}
