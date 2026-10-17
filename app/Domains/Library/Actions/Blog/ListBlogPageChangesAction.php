<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Models\LibraryBlogTheme;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\Library\Support\BlogPage;

/**
 * BLOG_PLAN BL3a: the blog pages waiting for the office, oldest first, each
 * with what it shows now and what it would show — the office approves the
 * whole set or sends it back with a note.
 */
class ListBlogPageChangesAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(): array
    {
        $names = LibraryBlogTheme::query()->get()->mapWithKeys(fn (LibraryBlogTheme $theme) => [$theme->key => $theme->name()]);

        return WriterProfile::query()
            ->whereNotNull('page_submitted_at')
            ->orderBy('page_submitted_at')
            ->get()
            ->map(function (WriterProfile $profile) use ($names) {
                $live = BlogPage::live($profile->only(['theme_key', 'accent_color', 'heading_font', 'tagline', 'bio', 'languages', 'sections']));
                $next = BlogPage::normalize((array) $profile->pending_page);
                $describe = fn (array $design) => $design + [
                    'theme_name' => $names[$design['theme']] ?? $design['theme'],
                    'colors' => BlogPage::colors($design),
                ];

                return [
                    'id' => $profile->id,
                    'display_name' => $profile->display_name,
                    'slug' => $profile->slug,
                    'student' => $profile->isStudent(),
                    'submitted_at' => $profile->page_submitted_at?->toDateTimeString(),
                    'live' => $describe($live),
                    'next' => $describe($next),
                ];
            })
            ->values()
            ->all();
    }
}
