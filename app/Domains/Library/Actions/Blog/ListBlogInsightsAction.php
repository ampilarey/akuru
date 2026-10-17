<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Enums\BlogRequestStatus;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Models\BlogRequest;
use App\Domains\Library\Models\LibraryComment;
use App\Domains\Library\Models\LibraryFollow;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryReadingEvent;
use App\Domains\Library\Models\LibraryTip;
use App\Domains\Library\Models\WriterEarning;
use App\Domains\Library\Models\WriterProfile;

/**
 * BLOG_PLAN BL7: the blogs at a glance for the Library office — how many,
 * what was published, followed and tipped this month, how old the oldest
 * thing in each checking queue is, and the recent posts to feature.
 */
class ListBlogInsightsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        $month = now()->startOfMonth();
        $posts = LibraryItem::query()->where('content_type', LibraryContentType::Post->value);
        $oldest = fn ($query, string $column = 'created_at') => ['count' => (clone $query)->count(), 'oldest' => (clone $query)->min($column)];

        $recent = LibraryItem::query()->with('writer')->where('content_type', LibraryContentType::Post->value)->where('status', 'published')
            ->where('published_at', '>=', now()->subDays(60))->orderByDesc('published_at')->limit(50)->get();
        $reads = $this->reads($recent->pluck('id')->all());
        $rewarded = WriterEarning::query()->where('kind', 'reward')->whereIn('library_item_id', $recent->pluck('id'))->pluck('writer_amount', 'library_item_id');

        return [
            'totals' => [
                'blogs' => WriterProfile::query()->where('status', 'active')->where(fn ($q) => $q->where('kind', 'student')->orWhereHas('items', fn ($i) => $i->where('content_type', 'post')))->count(),
                'students' => WriterProfile::query()->where('status', 'active')->where('kind', 'student')->count(),
                'posts_published' => (clone $posts)->where('status', 'published')->count(),
                'posts_this_month' => (clone $posts)->where('status', 'published')->where('published_at', '>=', $month)->count(),
                'follows' => LibraryFollow::query()->count(),
                'tips_this_month' => round((float) LibraryTip::query()->where('status', 'paid')->whereNull('reverses_tip_id')->where('paid_at', '>=', $month)->sum('amount'), 2),
                'rewards_this_month' => round((float) WriterEarning::query()->where('kind', 'reward')->where('created_at', '>=', $month)->sum('writer_amount'), 2),
            ],
            'queues' => [
                'requests' => $oldest(BlogRequest::query()->whereIn('status', [BlogRequestStatus::WaitingParent->value, BlogRequestStatus::WaitingOffice->value])),
                'teacher' => $oldest((clone $posts)->where('review_stage', 'teacher'), 'submitted_at'),
                'office' => $oldest((clone $posts)->where('review_stage', 'office'), 'submitted_at'),
                'pages' => $oldest(WriterProfile::query()->whereNotNull('page_submitted_at'), 'page_submitted_at'),
                'comments' => $oldest(LibraryComment::query()->whereIn('status', ['pending', 'reported'])),
                'tip_notes' => $oldest(LibraryTip::query()->where('note_status', 'pending')),
            ],
            'recent' => $recent->map(fn (LibraryItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'slug' => $item->slug,
                'blog' => $item->writer?->display_name,
                'student' => $item->writer?->isStudent() ?? false,
                'published_at' => $item->published_at?->toDateString(),
                'reads' => $reads[$item->id] ?? 0,
                'featured' => (bool) $item->featured && $item->featured_at?->gte($month),
                'reward' => isset($rewarded[$item->id]) ? (float) $rewarded[$item->id] : null,
            ])->values()->all(),
        ];
    }

    /**
     * How many readers opened each post: distinct sessions in the reading log.
     *
     * @param  list<int>  $itemIds
     * @return array<int, int>
     */
    public function reads(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return LibraryReadingEvent::query()->whereIn('library_item_id', $itemIds)
            ->selectRaw('library_item_id, count(distinct coalesce(session_hash, user_id)) as readers')
            ->groupBy('library_item_id')->pluck('readers', 'library_item_id')->map(fn ($n) => (int) $n)->all();
    }
}
