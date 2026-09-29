<?php

namespace App\Domains\Library\Actions;

use App\Domains\Library\Models\WriterProfile;
use App\Domains\Media\Actions\ResolvePublicMediaUrlAction;

/**
 * R5 (RESEARCH_ARTICLES_PLAN): the shelf's *Authors* — every active writer
 * with a public page and at least one published work, most published first.
 * The header's and footer's Authors link lands here (`/library#authors`);
 * each name opens the writer's own page (L8).
 */
class ListLibraryAuthorsAction
{
    /**
     * @return list<array{display_name: string, slug: string, expertise: ?string, photo_url: ?string, published: int}>
     */
    public function execute(int $limit = 24): array
    {
        $media = app(ResolvePublicMediaUrlAction::class);

        return WriterProfile::query()
            ->where('status', 'active')
            ->whereNotNull('slug')
            ->withCount(['items as published_count' => fn ($query) => $query->where('status', 'published')])
            ->having('published_count', '>', 0)
            ->orderByDesc('published_count')
            ->orderBy('display_name')
            ->limit($limit)
            ->get()
            ->map(fn (WriterProfile $profile): array => [
                'display_name' => (string) $profile->display_name,
                'slug' => (string) $profile->slug,
                'expertise' => $profile->expertise,
                'photo_url' => $profile->photo_media_file_id ? $media->execute((int) $profile->photo_media_file_id) : null,
                'published' => (int) $profile->published_count,
            ])
            ->values()
            ->all();
    }
}
