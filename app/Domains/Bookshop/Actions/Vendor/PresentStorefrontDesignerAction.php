<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\Shop\ResolveStorefrontAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorStorefront;
use App\Domains\Bookshop\Models\VendorStorefrontVersion;
use App\Domains\Bookshop\Support\Identity;
use App\Domains\Bookshop\Support\Theme;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;

/**
 * Everything the designer screen shows (BOOKSHOP_PLAN §6): the draft
 * identity and theme with its image previews, whether the draft reads
 * (the contrast report), what is published and when, the versions to roll
 * back to, and the choices on offer — presets (locked ones marked), fonts,
 * scales and shapes.
 *
 * @return array<string, mixed>
 */
class PresentStorefrontDesignerAction
{
    public function execute(VendorScope $scope): array
    {
        $vendor = Vendor::query()->findOrFail($scope->vendorId);
        $storefront = VendorStorefront::query()->where('vendor_id', $scope->vendorId)->first();
        $theme = Theme::normalize((array) ($storefront?->draft_theme ?? []), $vendor);
        $identity = ((array) ($storefront?->draft_identity ?? [])) + Identity::default();
        $images = app(ResolvePublicImageVariantAction::class);

        $previews = [];
        foreach (Identity::IMAGES as $slot) {
            $id = $identity['images'][$slot] ?? null;
            $previews[$slot] = is_numeric($id) ? $images->execute((int) $id, $slot === 'banner' ? ResolveStorefrontAction::BANNER_WIDTH : ResolveStorefrontAction::LOGO_WIDTH) : null;
        }

        $versions = $storefront === null ? [] : VendorStorefrontVersion::query()
            ->where('vendor_storefront_id', $storefront->id)
            ->orderByDesc('number')
            ->limit((int) config('bookshop.storefront.max_versions_shown', 20))
            ->get()
            ->map(fn (VendorStorefrontVersion $v) => [
                'id' => $v->id,
                'number' => $v->number,
                'note' => $v->note,
                'preset' => $v->theme['preset'] ?? null,
                'primary' => $v->theme['colors']['primary'] ?? null,
                'created_at' => $v->created_at?->toDateTimeString(),
                'live' => $storefront->published_version_id === $v->id,
            ])->values()->all();

        return [
            'exists' => $storefront !== null,
            'identity' => $identity,
            'image_previews' => $previews,
            'theme' => $theme,
            'problems' => Theme::problems($theme),
            'published_at' => $storefront?->published_at?->toDateTimeString(),
            'draft_dirty' => $storefront !== null && ($storefront->draft_theme !== $storefront->published_theme || $storefront->draft_identity !== $storefront->published_identity),
            'versions' => $versions,
            'options' => [
                'presets' => Theme::presetsFor($vendor),
                'fonts' => Theme::fonts(),
                'scales' => Theme::SCALES,
                'shapes' => Theme::SHAPES,
                'colors' => Theme::COLORS,
                'socials' => Identity::SOCIALS,
                'badges' => (array) ($vendor->badges ?? []),
            ],
        ];
    }
}
