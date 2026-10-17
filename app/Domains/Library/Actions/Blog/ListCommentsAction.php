<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\LibraryComment;
use App\Domains\Library\Models\LibraryItem;
use Illuminate\Support\Facades\DB;

/**
 * BLOG_PLAN BL5: comments as a post's readers, the office and the blog's
 * owner see them. Readers see only what the office published, with the
 * commenter's first name and initial; the owner's replies carry the pen name.
 */
class ListCommentsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forItem(LibraryItem $item): array
    {
        $rows = LibraryComment::query()->where('library_item_id', $item->id)->where('status', 'published')->orderBy('created_at')->get();
        $names = $this->names($rows->pluck('user_id')->all());
        $owner = (int) ($item->writer?->user_id ?? 0);
        $shape = fn (LibraryComment $c) => [
            'id' => $c->id,
            'body' => $c->body,
            'by' => (int) $c->user_id === $owner ? (string) $item->writer?->display_name : ($names[$c->user_id] ?? '—'),
            'owner' => (int) $c->user_id === $owner,
            'at' => $c->created_at?->toDateString(),
        ];

        return $rows->whereNull('parent_id')->map(fn (LibraryComment $c) => $shape($c) + [
            'replies' => $rows->where('parent_id', $c->id)->map($shape)->values()->all(),
        ])->values()->all();
    }

    /**
     * The office's list, oldest first; a status, or everything waiting.
     *
     * @return list<array<string, mixed>>
     */
    public function office(?string $status = null, int $limit = 300): array
    {
        $rows = LibraryComment::query()
            ->with('item.writer')
            ->when(in_array($status, LibraryComment::STATUSES, true), fn ($q) => $q->where('status', $status), fn ($q) => $q->whereIn('status', ['pending', 'reported']))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
        $users = DB::table('users')->whereIn('id', $rows->pluck('user_id')->unique()->all())->pluck('name', 'id');

        return $rows->map(fn (LibraryComment $c) => [
            'id' => $c->id,
            'post' => $c->item?->title,
            'slug' => $c->item?->slug,
            'blog' => $c->item?->writer?->display_name,
            'commenter' => (string) ($users[$c->user_id] ?? '—'),
            'reply' => $c->parent_id !== null,
            'body' => $c->body,
            'status' => $c->status,
            'report_count' => (int) $c->report_count,
            'decision_note' => $c->decision_note,
            'created_at' => $c->created_at?->toDateTimeString(),
        ])->values()->all();
    }

    /**
     * The comments on a blog's posts, for its owner on *My blog* — read only;
     * the office decides.
     *
     * @return list<array{post: ?string, body: string, status: string, at: ?string}>
     */
    public function forWriter(int $writerId, int $limit = 20): array
    {
        return LibraryComment::query()
            ->with('item')
            ->whereHas('item', fn ($q) => $q->where('writer_id', $writerId))
            ->whereIn('status', ['pending', 'published'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LibraryComment $c) => ['post' => $c->item?->title, 'body' => $c->body, 'status' => $c->status, 'at' => $c->created_at?->toDateString()])
            ->values()->all();
    }

    /**
     * First name and initial: a comment never carries a reader's full name.
     *
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    private function names(array $userIds): array
    {
        return DB::table('users')->whereIn('id', array_unique($userIds))->pluck('name', 'id')
            ->map(function ($name) {
                $parts = preg_split('/\s+/u', trim((string) $name)) ?: [];
                $first = $parts[0] ?? '';
                $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1).'.' : '';

                return trim($first.' '.$last) ?: '—';
            })->all();
    }
}
