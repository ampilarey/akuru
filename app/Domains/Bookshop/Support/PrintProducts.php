<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Enums\ProductStatus;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\Vendor;

/**
 * PRINT_PRODUCTS_PLAN PP2 (PD3): what the print desk sells. Each is a
 * Bookstore product in *Print*, made by `PrintDesk::ensure()`, with its own
 * door (`/shop/print/{path}`), layouts and templates. Name tags is the one
 * NT1–NT7 built, and the desk's switch still turns it on; the others are
 * put on sale one by one while the desk is on.
 */
final class PrintProducts
{
    public const DEFAULT = 'name_tags';

    /** kind => slug, door path, and the product's names. */
    public const KINDS = [
        'name_tags' => [
            'slug' => PrintDesk::PRODUCT_SLUG, 'path' => 'name-tags',
            'title' => 'Name tags — A4', 'title_dv' => 'ނަން ޓެގް — A4', 'title_ar' => 'بطاقات أسماء — A4',
            'summary' => 'Name tags and subject tags, designed by you, printed by Akuru.',
            'summary_dv' => 'ތިބާ ފަރުމާކޮށް އަކުރުން ޕްރިންޓްކުރާ ނަން ޓެގާއި މާއްދާ ޓެގް.',
            'summary_ar' => 'بطاقات أسماء ومواد، تصمّمها أنت وتطبعها أكورو.',
        ],
        'cards' => [
            'slug' => 'print-cards', 'path' => 'cards',
            'title' => 'Cards', 'title_dv' => 'ކާޑު', 'title_ar' => 'بطاقات',
            'summary' => 'Business cards, place cards and invitations, designed by you, printed by Akuru.',
            'summary_dv' => 'ތިބާ ފަރުމާކޮށް އަކުރުން ޕްރިންޓްކުރާ ވިޔަފާރީ ކާޑު، ނަން ކާޑު އަދި ދަޢުވަތު ކާޑު.',
            'summary_ar' => 'بطاقات عمل وبطاقات أماكن ودعوات، تصمّمها أنت وتطبعها أكورو.',
        ],
        'stickers' => [
            'slug' => 'print-stickers', 'path' => 'stickers',
            'title' => 'Stickers', 'title_dv' => 'ސްޓިކަރު', 'title_ar' => 'ملصقات',
            'summary' => 'Stickers on sheets, designed by you, printed by Akuru.',
            'summary_dv' => 'ތިބާ ފަރުމާކޮށް އަކުރުން ޝީޓުގައި ޕްރިންޓްކުރާ ސްޓިކަރު.',
            'summary_ar' => 'ملصقات على أوراق، تصمّمها أنت وتطبعها أكورو.',
        ],
        'photos' => [
            'slug' => 'print-photos', 'path' => 'photos',
            'title' => 'Photo prints', 'title_dv' => 'ފޮޓޯ ޕްރިންޓް', 'title_ar' => 'طباعة الصور',
            'summary' => 'Your photos printed by Akuru, from 4 × 6 in to A3.',
            'summary_dv' => 'ތިބާގެ ފޮޓޯ އަކުރުން ޕްރިންޓް ކޮށްދެނީ، 4 × 6 އިންޗިން ފެށިގެން A3 އަށް.',
            'summary_ar' => 'صورك تطبعها أكورو، من 4 × 6 بوصة إلى A3.',
        ],
    ];

    /** @return list<string> */
    public static function kinds(): array
    {
        return array_keys(self::KINDS);
    }

    public static function valid(?string $kind): bool
    {
        return $kind !== null && isset(self::KINDS[$kind]);
    }

    public static function product(string $kind): ?Product
    {
        return self::valid($kind) ? Product::query()->where('slug', self::KINDS[$kind]['slug'])->first() : null;
    }

    /**
     * PP7a: a shop's own copy of a print product, slug `<print product>--<shop>`,
     * so its sales are the shop's orders and earn at its commission. Made
     * the first time it is wanted; on sale while the shop is approved to print.
     */
    public static function productFor(string $kind, Vendor $vendor): ?Product
    {
        if (! self::valid($kind)) {
            return null;
        }
        if ($vendor->kind === 'akuru') {
            return self::product($kind);
        }
        $meta = self::KINDS[$kind];
        $slug = $meta['slug'].'--'.$vendor->slug;
        $base = self::product($kind) ?? (PrintDesk::ensure() ? self::product($kind) : null);

        return Product::query()->where('slug', $slug)->first() ?? Product::query()->create([
            'vendor_id' => $vendor->id,
            'product_category_id' => $base?->product_category_id,
            'slug' => $slug,
            ...collect($meta)->only(['title', 'title_dv', 'title_ar', 'summary', 'summary_dv', 'summary_ar'])->all(),
            'price' => 0, 'currency' => 'MVR', 'tax_class' => 'standard', 'track_stock' => false, 'stock' => 0,
            'status' => $vendor->print_enabled ? 'active' : 'draft', 'visibility' => 'shop',
        ]);
    }

    /** PP7a: who prints what a product sells — null for Akuru (or anything else), a shop's id for its copy. */
    public static function printerOfProduct(?int $productId): ?int
    {
        if ($productId === null) {
            return null;
        }
        $product = Product::query()->whereKey($productId)->first(['id', 'slug', 'vendor_id']);
        if ($product === null || ! str_contains((string) $product->slug, '--') || self::kindOfSlug($product->slug) === null) {
            return null;
        }

        return (int) $product->vendor_id;
    }

    /** The kind a product is, or null for anything not printed to order — a shop's copy too (PP7a). */
    public static function kindOfSlug(?string $slug): ?string
    {
        $slug = $slug === null ? null : explode('--', $slug, 2)[0];
        foreach (self::KINDS as $kind => $meta) {
            if ($meta['slug'] === $slug) {
                return $kind;
            }
        }

        return null;
    }

    public static function kindOfPath(string $path): ?string
    {
        foreach (self::KINDS as $kind => $meta) {
            if ($meta['path'] === $path) {
                return $kind;
            }
        }

        return null;
    }

    public static function onSale(string $kind): bool
    {
        return self::product($kind)?->status === ProductStatus::Active;
    }

    /** Open to customers: the desk on, and the product on sale — name tags whenever the desk is on (its switch). */
    public static function open(string $kind): bool
    {
        return PrintDesk::settings()['enabled'] && ($kind === self::DEFAULT || self::onSale($kind));
    }

    /** The product's name in the page's language. */
    public static function title(string $kind, string $locale): string
    {
        $meta = self::KINDS[$kind] ?? self::KINDS[self::DEFAULT];
        $name = $locale === 'dv' ? $meta['title_dv'] : ($locale === 'ar' ? $meta['title_ar'] : null);

        return $name ?: $meta['title'];
    }
}
