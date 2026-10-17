<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryComment;
use App\Domains\Library\Models\LibraryItem;
use Illuminate\Validation\ValidationException;

/**
 * BLOG_PLAN BL5 (§3.5): a signed-in reader comments on a published post, or
 * the blog's owner replies to a published comment. Plain text, up to 1,000
 * characters. Every comment waits for the office (`pending`); nothing shows
 * before it is approved. A blog with comments off, or an under-13's, takes
 * none (D13).
 */
class PostCommentAction
{
    public const MAX = 1000;

    public function execute(int $userId, int $itemId, string $body, ?int $parentId = null): LibraryComment
    {
        $item = LibraryItem::query()->with('writer')->findOrFail($itemId);
        if ($item->content_type !== LibraryContentType::Post || $item->status !== LibraryItemStatus::Published || $item->writer === null) {
            throw ValidationException::withMessages(['comment' => __('blog.error_comment_closed')]);
        }
        if (! app(BlogPageRulesAction::class)->commentsOpen($item->writer)) {
            throw ValidationException::withMessages(['comment' => __('blog.error_comment_closed')]);
        }
        $text = trim(strip_tags($body));
        if ($text === '' || mb_strlen($text) > self::MAX) {
            throw ValidationException::withMessages(['comment' => __('blog.error_comment_length', ['max' => self::MAX])]);
        }

        if ($parentId !== null) {
            // Only the blog's owner replies, and only to a published comment on this post.
            $parent = LibraryComment::query()->whereKey($parentId)->where('library_item_id', $item->id)->whereNull('parent_id')->where('status', 'published')->first();
            if ($parent === null || (int) $item->writer->user_id !== $userId) {
                throw ValidationException::withMessages(['comment' => __('blog.error_reply_owner')]);
            }
        }

        $comment = LibraryComment::query()->create([
            'library_item_id' => $item->id, 'user_id' => $userId, 'parent_id' => $parentId, 'body' => $text, 'status' => 'pending',
        ]);
        app(NotifyLibraryUserAction::class)->office('A blog comment to check', 'A comment on "'.$item->title.'" waits for checking.', '/admin/library/comments');

        return $comment;
    }
}
