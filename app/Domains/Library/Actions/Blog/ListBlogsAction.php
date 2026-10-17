<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\ListLibraryItemsAction;
use App\Domains\Library\Enums\LibraryContentType;
use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\WriterProfile;

/**
 * BLOG_PLAN BL2b (§3): the blogs' front, `/library/blogs` — the latest
 * published posts, newest first, and the blogs that have published one.
 * Only what the office published is here; a student is shown by pen name.
 */
class ListBlogsAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function latestPosts(int $limit = 24, ?string $language = null): array
    {
        $serialize = app(ListLibraryItemsAction::class);

        return LibraryItem::query()
            ->with(['category', 'tags', 'authors', 'writer'])
            ->where('content_type', LibraryContentType::Post->value)
            ->where('status', LibraryItemStatus::Published->value)
            ->whereHas('writer', fn ($query) => $query->where('status', 'active'))
            ->when(in_array($language, ['en', 'dv', 'ar'], true), fn ($query) => $query->where('language', $language))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (LibraryItem $item) => $serialize->serialize($item) + [
                'excerpt' => $this->excerpt((string) $item->body),
                'student' => $item->writer?->isStudent() ?? false,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{display_name: string, blog_title: ?string, slug: string, student: bool, posts: int, latest_at: ?string}>
     */
    public function blogs(): array
    {
        $counts = LibraryItem::query()
            ->where('content_type', LibraryContentType::Post->value)
            ->where('status', LibraryItemStatus::Published->value)
            ->whereNotNull('writer_id')
            ->groupBy('writer_id')
            ->selectRaw('writer_id, count(*) as posts, max(published_at) as latest_at')
            ->get()
            ->keyBy('writer_id');

        return WriterProfile::query()
            ->whereIn('id', $counts->keys()->all())
            ->where('status', 'active')
            ->whereNotNull('slug')
            ->get()
            ->sortByDesc(fn (WriterProfile $profile) => (string) $counts[$profile->id]->latest_at)
            ->map(fn (WriterProfile $profile) => [
                'display_name' => (string) $profile->display_name,
                'blog_title' => $profile->blog_title,
                'slug' => (string) $profile->slug,
                'student' => $profile->isStudent(),
                'posts' => (int) $counts[$profile->id]->posts,
                'latest_at' => $counts[$profile->id]->latest_at ? substr((string) $counts[$profile->id]->latest_at, 0, 10) : null,
            ])
            ->values()
            ->all();
    }

    public function excerpt(string $html, int $length = 180): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5)) ?? '');

        return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length)).'…' : $text;
    }
}
