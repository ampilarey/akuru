<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Courses\Models\Course;

class ListPublicCourseSitemapEntriesAction
{
    /**
     * Open and upcoming courses for the public sitemap.
     *
     * @return list<array{slug: string, lastmod: string}>
     */
    public function execute(): array
    {
        return Course::query()
            ->whereIn('status', ['open', 'upcoming'])
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at'])
            ->map(fn (Course $course): array => [
                'slug' => (string) $course->slug,
                'lastmod' => $course->updated_at?->toDateString() ?? now()->toDateString(),
            ])
            ->values()
            ->all();
    }
}
