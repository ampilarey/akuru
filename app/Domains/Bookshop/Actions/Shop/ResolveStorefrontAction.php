<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\Identity;
use App\Domains\Bookshop\Support\ShopPresenter;
use App\Domains\Bookshop\Support\Theme;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;

/**
 * A storefront as the page renders it (BOOKSHOP_PLAN §6, §10 "Storefront
 * rendering"): the identity in the visitor's language with its images
 * resolved to public URLs, the theme as CSS custom properties, and the
 * fonts to load. The published copy for the public; the draft for the
 * vendor's preview. Null when nothing has been published, so the vendor
 * page stays the plain B1b one.
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
        if ($storefront === null) {
            return null;
        }
        $identity = $draft ? $storefront->draft_identity : $storefront->published_identity;
        $theme = $draft ? $storefront->draft_theme : $storefront->published_theme;
        if ($theme === null || (! $draft && ! $storefront->isPublished())) {
            return null;
        }
        $theme = Theme::normalize((array) $theme, $vendor);
        $identity = ((array) $identity) + Identity::default();
        $images = app(ResolvePublicImageVariantAction::class);
        $locale = app()->getLocale();
        $local = fn (string $key, ?string $fallback) => in_array($locale, ['dv', 'ar'], true) && ! empty($identity[$key.'_'.$locale]) ? $identity[$key.'_'.$locale] : $fallback;
        $badges = array_values(array_filter(['verified', 'akuru_partner'], fn ($b) => $vendor->hasBadge($b)));

        return [
            'draft' => $draft,
            'name' => $local('name', $vendor->name),
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
        ];
    }

    private function image(ResolvePublicImageVariantAction $images, mixed $mediaId, int $width): ?string
    {
        return is_int($mediaId) || (is_string($mediaId) && ctype_digit($mediaId)) ? $images->execute((int) $mediaId, $width) : null;
    }
}
