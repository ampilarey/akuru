<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Models\LibraryItem;

/**
 * BLOG_PLAN BL5 (§3.3, §3.5): what a published post's comments section shows
 * a reader — the published comments and replies, and whether the box is
 * open: signed in, comments on, never on an under-13's blog. Null for an item
 * that is not a post.
 */
class PresentPostCommentsAction
{
    /**
     * @return array{item_id: int, open: bool, under_13: bool, signed_in: bool, owner: bool, list: list<array<string, mixed>>}|null
     */
    public function execute(string $slug, ?int $userId): ?array
    {
        $item = LibraryItem::query()->with('writer')->where('slug', $slug)->where('status', 'published')->first();
        if ($item === null || $item->content_type !== LibraryContentType::Post || $item->writer === null) {
            return null;
        }
        $rules = app(BlogPageRulesAction::class);

        return [
            'item_id' => (int) $item->id,
            'open' => $rules->commentsOpen($item->writer),
            'under_13' => $rules->underThirteen($item->writer),
            'signed_in' => $userId !== null,
            'owner' => $userId !== null && (int) $item->writer->user_id === $userId,
            'list' => app(ListCommentsAction::class)->forItem($item),
        ];
    }
}
