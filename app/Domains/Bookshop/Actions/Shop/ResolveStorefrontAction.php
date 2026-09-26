<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorPage;
use App\Domains\Bookshop\Support\Identity;
use App\Domains\Bookshop\Support\ShopPresenter;
use App\Domains\Bookshop\Support\Theme;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;
use Illuminate\Support\Facades\Cache;

/**
 * A storefront as the page renders it (BOOKSHOP_PLAN §6, §10 "Storefront
 * rendering"): the identity in the visitor's language with its images
 * resolved to public URLs, the theme as CSS custom properties, the fonts
 * to load, and — since B5 — the home's sections drawn, the storefront menu
 * resolved to addresses, and the SEO fields. The published copy for the
 * public, cached per vendor and language for a few minutes (§10) and
 * cleared on publish and on moderation; the draft, never cached, for the
 * vendor's preview. Null when nothing has been published or the office
 * has taken the storefront down (§6.6), so the vendor page stays the
 * plain B1b one.
 */
class ResolveStorefrontAction
{
    public const LOGO_WIDTH = 240;

    public const BANNER_WIDTH = 1600;

    /**
     * @return array<string, mixed>|null
     */
    public function execute(Vendor $vendor, bool $draft = false): ?array
    {
        $storefront = $vendor->storefront;
        if ($storefront === null || (! $draft && ! $storefront->isLive())) {
            return null;
        }
        if ($draft) {
            return $this->build($vendor, true);
        }

        return Cache::remember(self::key($vendor->id, 'home'), (int) config('bookshop.storefront.cache_seconds', 600), fn () => $this->build($vendor, false));
    }

    /**
     * A page under the storefront (§6.4): its title, its sections drawn and
     * its SEO fields. Null when the page is not published (unless drafting).
     *
     * @return array<string, mixed>|null
     */
    public function page(Vendor $vendor, VendorPage $page, bool $draft = false): ?array
    {
        if (! $draft && $page->published_at === null) {
            return null;
        }
        $build = fn () => [
            'slug' => $page->slug,
            'title' => $page->localizedTitle(),
            'url' => route('public.shop.vendor.page', [$vendor->slug, $page->slug]),
            'sections' => app(RenderSectionsAction::class)->execute($vendor, (array) (($draft ? $page->draft_sections : $page->published_sections) ?? []), $draft),
            'seo' => $this->seo((array) ($page->seo ?? []), $page->localizedTitle()),
        ];

        return $draft ? $build() : Cache::remember(self::key($vendor->id, 'page.'.$page->id), (int) config('bookshop.storefront.cache_seconds', 600), $build);
    }

    /** Clear every cached copy of this vendor's storefront and pages, in every language. */
    public function forget(int $vendorId): void
    {
        $pageIds = VendorPage::query()->where('vendor_id', $vendorId)->pluck('id')->all();
        foreach (array_keys((array) config('laravellocalization.supportedLocales', ['en' => []])) as $locale) {
            Cache::forget(self::key($vendorId, 'home', $locale));
            foreach ($pageIds as $pageId) {
                Cache::forget(self::key($vendorId, 'page.'.$pageId, $locale));
            }
        }
    }

    public static function key(int $vendorId, string $what, ?string $locale = null): string
    {
        return 'bookshop.storefront.'.$vendorId.'.'.$what.'.'.($locale ?? app()->getLocale());
    }

    /**
     * @return array<string, mixed>
     */
    private function build(Vendor $vendor, bool $draft): array
    {
        $storefront = $vendor->storefront;
        $identity = ((array) ($draft ? $storefront->draft_identity : $storefront->published_identity)) + Identity::default();
        $theme = Theme::normalize((array) ($draft ? $storefront->draft_theme : $storefront->published_theme), $vendor);
        $images = app(ResolvePublicImageVariantAction::class);
        $locale = app()->getLocale();
        $local = fn (string $key, ?string $fallback) => in_array($locale, ['dv', 'ar'], true) && ! empty($identity[$key.'_'.$locale]) ? $identity[$key.'_'.$locale] : $fallback;
        $badges = array_values(array_filter(['verified', 'akuru_partner'], fn ($b) => $vendor->hasBadge($b)));
        $render = app(RenderSectionsAction::class);
        $name = $local('name', $vendor->name);

        return [
            'draft' => $draft,
            'name' => $name,
            'tagline' => $local('tagline', $vendor->tagline),
            'logo' => $this->image($images, $identity['images']['logo'] ?? null, self::LOGO_WIDTH),
            'logo_dark' => $this->image($images, $identity['images']['logo_dark'] ?? null, self::LOGO_WIDTH),
            'banner' => $this->image($images, $identity['images']['banner'] ?? null, self::BANNER_WIDTH),
            'story' => $local('story', $identity['story']),
            'contact' => (array) $identity['contact'],
            'hours' => $identity['hours'],
            'socials' => array_filter((array) $identity['socials']),
            'badges' => $badges,
            'theme' => $theme,
            'css' => Theme::cssVariables($theme),
            'dark_css' => Theme::darkCssVariables($theme),
            'fonts_url' => Theme::fontsUrl($theme),
            'published_at' => $storefront->published_at?->toDateTimeString(),
            'card_width' => ShopPresenter::CARD_WIDTH,
            'sections' => $render->execute($vendor, (array) (($draft ? $storefront->draft_sections : $storefront->published_sections) ?? []), $draft),
            'navigation' => $render->links($vendor, (array) (($draft ? $storefront->draft_navigation : $storefront->published_navigation) ?? []), $draft),
            'seo' => $this->seo((array) (($draft ? $storefront->draft_seo : $storefront->published_seo) ?? []), $name),
            'home_url' => route('public.shop.vendor', $vendor->slug),
        ];
    }

    /**
     * @return array{title: string, description: ?string, image: ?string}
     */
    private function seo(array $seo, string $fallbackTitle): array
    {
        return [
            'title' => is_string($seo['title'] ?? null) && $seo['title'] !== '' ? $seo['title'] : $fallbackTitle,
            'description' => is_string($seo['description'] ?? null) && $seo['description'] !== '' ? $seo['description'] : null,
            'image' => $this->image(app(ResolvePublicImageVariantAction::class), $seo['image'] ?? null, self::BANNER_WIDTH),
        ];
    }

    private function image(ResolvePublicImageVariantAction $images, mixed $mediaId, int $width): ?string
    {
        return is_int($mediaId) || (is_string($mediaId) && ctype_digit($mediaId)) ? $images->execute((int) $mediaId, $width) : null;
    }
}
