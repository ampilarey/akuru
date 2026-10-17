<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Enums\LibraryItemStatus;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPostSeries;

/**
 * BLOG_PLAN BL2b (§4.3): where a published post sits in its series — *part
 * n of m*, with the previous and next parts. Only published parts count, so
 * a part still being checked leaves no gap a reader can fall into.
 */
class PresentPostSeriesAction
{
    /**
     * @return array{title: string, position: int, count: int, previous: ?array{title: string, slug: string}, next: ?array{title: string, slug: string}}|null
     */
    public function execute(LibraryItem $item): ?array
    {
        if ($item->series_id === null) {
            return null;
        }
        $series = LibraryPostSeries::query()->find($item->series_id);
        if ($series === null) {
            return null;
        }
        $parts = LibraryItem::query()
            ->where('series_id', $series->id)
            ->where('status', LibraryItemStatus::Published->value)
            ->orderBy('series_position')
            ->orderBy('id')
            ->get(['id', 'title', 'slug'])
            ->values();
        $index = $parts->search(fn (LibraryItem $part) => $part->id === $item->id);
        if ($index === false) {
            return null;
        }
        $link = fn (?LibraryItem $part) => $part ? ['title' => (string) $part->title, 'slug' => (string) $part->slug] : null;

        return [
            'title' => (string) $series->title,
            'position' => $index + 1,
            'count' => $parts->count(),
            'previous' => $link($parts->get($index - 1)),
            'next' => $link($parts->get($index + 1)),
        ];
    }

    /**
     * A writer's series with their published parts, in order, for the blog
     * page.
     *
     * @return list<array{title: string, posts: list<array{title: string, slug: string}>}>
     */
    public function forWriter(int $writerId): array
    {
        return LibraryPostSeries::query()
            ->where('writer_id', $writerId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (LibraryPostSeries $series) => [
                'title' => (string) $series->title,
                'posts' => LibraryItem::query()
                    ->where('series_id', $series->id)
                    ->where('status', LibraryItemStatus::Published->value)
                    ->orderBy('series_position')
                    ->orderBy('id')
                    ->get(['title', 'slug'])
                    ->map(fn (LibraryItem $part) => ['title' => (string) $part->title, 'slug' => (string) $part->slug])
                    ->all(),
            ])
            ->filter(fn (array $series) => $series['posts'] !== [])
            ->values()
            ->all();
    }
}
