<?php

namespace App\Domains\Library\Actions\Blog;

use App\Domains\Library\Actions\SaveWriterPublicProfileAction;
use App\Domains\Library\Models\LibraryBlogTheme;
use App\Domains\Library\Models\WriterProfile;
use App\Domains\Library\Support\BlogPage;
use App\Domains\Media\Actions\ResolvePublicMediaUrlAction;

/**
 * BLOG_PLAN BL3a (§5): what the page designer shows a blogger — the gallery,
 * the choices, the live design and the one waiting, where it stands, and a
 * few published posts to preview with. Null for someone with no blog.
 */
class PresentBlogDesignerAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(int $userId): ?array
    {
        $profile = WriterProfile::query()->where('user_id', $userId)->where('status', 'active')->first();
        if ($profile === null) {
            return null;
        }
        $live = BlogPage::live($profile->only(BlogPage::LIVE_COLUMNS));

        return [
            'blog' => ['pen_name' => $profile->display_name, 'blog_title' => $profile->blog_title, 'slug' => $profile->slug, 'student' => $profile->isStudent()],
            'themes' => LibraryBlogTheme::query()->where('active', true)->orderBy('sort_order')->get()
                ->map(fn (LibraryBlogTheme $theme) => ['key' => $theme->key, 'name' => $theme->name(), 'colors' => BlogPage::colors(['theme' => $theme->key, 'accent' => null])])
                ->values()->all(),
            'fonts' => BlogPage::FONTS,
            'sections' => BlogPage::SECTIONS,
            'limits' => ['tagline' => BlogPage::TAGLINE_MAX, 'about' => BlogPage::ABOUT_MAX],
            'live' => $live,
            'draft' => $profile->pending_page !== null ? BlogPage::normalize((array) $profile->pending_page) : null,
            'state' => $profile->page_submitted_at !== null ? 'waiting' : ($profile->pending_page !== null ? 'draft' : 'live'),
            'decision_note' => $profile->page_decision_note,
            'posts' => array_slice(app(ListBlogsAction::class)->latestPostsFor((int) $profile->id), 0, 3),
            // BL3b: the images the form starts from — the live ones public, a waiting one through the owner's route.
            'images' => $this->images($profile, $live, $profile->pending_page !== null ? BlogPage::normalize((array) $profile->pending_page) : $live),
            'link_keys' => SaveWriterPublicProfileAction::LINK_KEYS,
            'links_max' => BlogPage::LINKS_MAX,
            'photo_allowed' => app(BlogPageRulesAction::class)->photoAllowed($profile),
            'links_allowed' => app(BlogPageRulesAction::class)->linksAllowed($profile),
        ];
    }

    /**
     * @param  array<string, mixed>  $live
     * @param  array<string, mixed>  $shown
     * @return array{banner: ?string, photo: ?string}
     */
    public function images(WriterProfile $profile, array $live, array $shown, string $route = 'my-blog.design.image'): array
    {
        $out = [];
        foreach (['banner', 'photo'] as $kind) {
            $id = $shown[$kind] ?? null;
            $out[$kind] = match (true) {
                $id === null => null,
                $id === ($live[$kind] ?? null) => app(ResolvePublicMediaUrlAction::class)->execute($id),
                default => route($route, $route === 'my-blog.design.image' ? ['kind' => $kind, 'v' => $id] : ['profile' => $profile->id, 'kind' => $kind, 'v' => $id], false),
            };
        }

        return $out;
    }
}
