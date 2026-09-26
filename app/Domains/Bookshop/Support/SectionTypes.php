<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Bookshop\Models\VendorPage;
use App\Domains\Bookshop\Models\VendorStorefrontImage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The section types a storefront is built from (BOOKSHOP_PLAN §6.3, the v1
 * set) and what each may hold. A section is structured data — this class
 * is the only thing that decides what a setting can be, so nothing a
 * vendor types reaches the page unchecked: text is trimmed and bounded,
 * prose is cleaned, images come from the shop's own library, products,
 * collections and pages must be the shop's own, a video is a URL on an
 * allowed host and never markup, a map is a pair of coordinates.
 *
 * Field kinds: text (with Dhivehi and Arabic variants), rich, image,
 * images:N, products:N, collection, buttons:N, link, choice:a,b, bool,
 * date, float, int:min,max, video, quotes:N, faq:N.
 */
final class SectionTypes
{
    public const TYPES = [
        'hero' => ['heading' => 'text', 'subheading' => 'text', 'images' => 'images:5', 'buttons' => 'buttons:2', 'align' => 'choice:start,center'],
        'announcement' => ['text' => 'text', 'ends_at' => 'date', 'link' => 'link'],
        'featured_products' => ['heading' => 'text', 'products' => 'products:12', 'layout' => 'choice:grid,carousel'],
        'collection' => ['heading' => 'text', 'collection' => 'collection', 'count' => 'choice:4,8,12', 'see_all' => 'bool'],
        'category_tiles' => ['heading' => 'text'],
        'new_arrivals' => ['heading' => 'text', 'count' => 'choice:4,8,12'],
        'best_sellers' => ['heading' => 'text', 'count' => 'choice:4,8,12'],
        'text_image' => ['heading' => 'text', 'body' => 'rich', 'image' => 'image', 'image_side' => 'choice:start,end'],
        'gallery' => ['heading' => 'text', 'images' => 'images:12'],
        'testimonials' => ['heading' => 'text', 'items' => 'quotes:6'],
        'faq' => ['heading' => 'text', 'items' => 'faq:12'],
        'delivery_returns' => ['heading' => 'text', 'body' => 'rich'],
        'contact_map' => ['heading' => 'text', 'lat' => 'float', 'lng' => 'float', 'zoom' => 'int:10,18'],
        'video' => ['heading' => 'text', 'url' => 'video'],
    ];

    public const VISIBILITIES = ['published', 'hidden', 'scheduled'];

    public const LINK_KINDS = ['all', 'collection', 'page', 'product'];

    public const TEXT_MAX = 300;

    /**
     * @return array<string, array<string, string>>
     */
    public static function schema(): array
    {
        return self::TYPES;
    }

    /**
     * @param  list<mixed>  $input
     * @param  list<string>  $locked  types the office has locked for this vendor
     * @return list<array<string, mixed>>
     */
    public static function normalize(array $input, int $vendorId, array $locked = []): array
    {
        $max = (int) config('bookshop.storefront.max_sections', 20);
        if (count($input) > $max) {
            throw ValidationException::withMessages(['sections' => __('shop.error_too_many_sections', ['max' => $max])]);
        }
        $out = [];
        foreach ($input as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $type = (string) ($raw['type'] ?? '');
            if (! array_key_exists($type, self::TYPES)) {
                throw ValidationException::withMessages(['sections' => __('shop.error_section_type', ['type' => $type])]);
            }
            if (in_array($type, $locked, true)) {
                throw ValidationException::withMessages(['sections' => __('shop.error_section_locked', ['type' => __('shop.section_'.$type)])]);
            }
            $settings = [];
            foreach (self::TYPES[$type] as $field => $kind) {
                $settings = $settings + self::field($field, $kind, (array) ($raw['settings'] ?? []), $vendorId);
            }
            $id = is_string($raw['id'] ?? null) && preg_match('/^s[a-z0-9]{6,12}$/', $raw['id']) ? $raw['id'] : 's'.Str::lower(Str::random(8));
            $visibility = in_array($raw['visibility'] ?? null, self::VISIBILITIES, true) ? $raw['visibility'] : 'published';
            $out[] = [
                'id' => $id,
                'type' => $type,
                'settings' => $settings,
                'visibility' => $visibility,
                'from' => $visibility === 'scheduled' ? self::date($raw['from'] ?? null) : null,
                'until' => $visibility === 'scheduled' ? self::date($raw['until'] ?? null) : null,
                'mobile_order' => is_numeric($raw['mobile_order'] ?? null) ? max(0, min(99, (int) $raw['mobile_order'])) : null,
            ];
        }

        return $out;
    }

    /** Is this section on the page right now? */
    public static function isVisible(array $section, bool $draft = false): bool
    {
        if ($draft) {
            return true;
        }

        return match ($section['visibility'] ?? 'published') {
            'hidden' => false,
            'scheduled' => (($section['from'] ?? null) === null || now()->toDateString() >= $section['from'])
                && (($section['until'] ?? null) === null || now()->toDateString() <= $section['until']),
            default => true,
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private static function field(string $field, string $kind, array $settings, int $vendorId): array
    {
        [$kind, $arg] = array_pad(explode(':', $kind, 2), 2, null);
        $value = $settings[$field] ?? null;
        $text = fn (mixed $v, int $max) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;

        return match ($kind) {
            'text' => [
                $field => $text($value, self::TEXT_MAX),
                $field.'_dv' => $text($settings[$field.'_dv'] ?? null, self::TEXT_MAX),
                $field.'_ar' => $text($settings[$field.'_ar'] ?? null, self::TEXT_MAX),
            ],
            'rich' => [
                $field => RichText::clean($value),
                $field.'_dv' => RichText::clean($settings[$field.'_dv'] ?? null),
                $field.'_ar' => RichText::clean($settings[$field.'_ar'] ?? null),
            ],
            'image' => [$field => self::ownImages([$value], $vendorId, 1)[0] ?? null],
            'images' => [$field => self::ownImages((array) $value, $vendorId, (int) $arg)],
            'products' => [$field => self::ownProducts((array) $value, $vendorId, (int) $arg)],
            'collection' => [$field => is_numeric($value) && VendorCollection::query()->where('vendor_id', $vendorId)->whereKey((int) $value)->exists() ? (int) $value : null],
            'buttons' => [$field => array_slice(array_values(array_filter(array_map(fn ($b) => self::link((array) $b, $vendorId, withLabel: true), array_values((array) $value)))), 0, (int) $arg)],
            'link' => [$field => self::link((array) $value, $vendorId, withLabel: false)],
            'choice' => [$field => in_array((string) $value, explode(',', (string) $arg), true) ? (string) $value : explode(',', (string) $arg)[0]],
            'bool' => [$field => (bool) $value],
            'date' => [$field => self::date($value)],
            'float' => [$field => is_numeric($value) && abs((float) $value) <= ($field === 'lat' ? 90 : 180) ? round((float) $value, 6) : null],
            'int' => [$field => is_numeric($value) ? max((int) explode(',', (string) $arg)[0], min((int) explode(',', (string) $arg)[1], (int) $value)) : (int) explode(',', (string) $arg)[0]],
            'video' => [$field => self::videoUrl($value)],
            'quotes' => [$field => array_slice(array_values(array_filter(array_map(fn ($q) => is_array($q) && $text($q['quote'] ?? null, 400) !== null ? ['quote' => $text($q['quote'], 400), 'name' => $text($q['name'] ?? null, 80)] : null, array_values((array) $value)))), 0, (int) $arg)],
            'faq' => [$field => array_slice(array_values(array_filter(array_map(fn ($q) => is_array($q) && $text($q['question'] ?? null, 200) !== null ? ['question' => $text($q['question'], 200), 'answer' => $text($q['answer'] ?? null, 2000)] : null, array_values((array) $value)))), 0, (int) $arg)],
            default => [$field => null],
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function link(array $raw, int $vendorId, bool $withLabel): ?array
    {
        $kind = (string) ($raw['kind'] ?? '');
        if (! in_array($kind, self::LINK_KINDS, true)) {
            return null;
        }
        $target = is_string($raw['target'] ?? null) ? Str::slug($raw['target']) : '';
        $ok = match ($kind) {
            'all' => true,
            'collection' => VendorCollection::query()->where('vendor_id', $vendorId)->where('slug', $target)->exists(),
            'page' => VendorPage::query()->where('vendor_id', $vendorId)->where('slug', $target)->exists(),
            'product' => ListShopProductsAction::forSale()->where('vendor_id', $vendorId)->where('slug', $target)->exists(),
        };
        if (! $ok) {
            return null;
        }
        $link = ['kind' => $kind, 'target' => $kind === 'all' ? null : $target];
        if ($withLabel) {
            $label = fn ($v) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, 60) : null;
            if ($label($raw['label'] ?? null) === null) {
                return null;
            }
            $link += ['label' => $label($raw['label']), 'label_dv' => $label($raw['label_dv'] ?? null), 'label_ar' => $label($raw['label_ar'] ?? null)];
        }

        return $link;
    }

    /**
     * @param  list<mixed>  $ids  media ids
     * @return list<int>
     */
    private static function ownImages(array $ids, int $vendorId, int $max): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));
        if ($ids === []) {
            return [];
        }
        $own = VendorStorefrontImage::query()->where('vendor_id', $vendorId)->whereIn('media_file_id', $ids)->pluck('media_file_id')->map(fn ($id) => (int) $id)->all();

        return array_slice(array_values(array_filter($ids, fn (int $id) => in_array($id, $own, true))), 0, $max);
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<int>
     */
    private static function ownProducts(array $ids, int $vendorId, int $max): array
    {
        $ids = array_values(array_unique(array_map('intval', array_filter($ids, 'is_numeric'))));
        if ($ids === []) {
            return [];
        }
        $own = ListShopProductsAction::forSale()->where('vendor_id', $vendorId)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return array_slice(array_values(array_filter($ids, fn (int $id) => in_array($id, $own, true))), 0, $max);
    }

    public static function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false ? $value : null;
    }

    /** A YouTube or Vimeo address, kept as given; the embed is built at render. */
    public static function videoUrl(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (! str_starts_with($value, 'http')) {
            $value = 'https://'.$value;
        }

        return self::videoEmbed($value) === null ? null : mb_substr($value, 0, 300);
    }

    /** The embed address for an allowed video URL, or null. */
    public static function videoEmbed(?string $url): ?string
    {
        $parts = parse_url((string) $url);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return null;
        }
        $host = strtolower(preg_replace('/^(www|m)\./', '', $parts['host']));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        parse_str((string) ($parts['query'] ?? ''), $query);
        if (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            $id = $query['v'] ?? (preg_match('#^(shorts|embed)/([A-Za-z0-9_-]{6,20})$#', $path, $m) ? $m[2] : null);
        } elseif ($host === 'youtu.be') {
            $id = $path;
        } elseif ($host === 'vimeo.com' || $host === 'player.vimeo.com') {
            return preg_match('#(?:^|/)(\d{6,12})$#', $path, $m) ? 'https://player.vimeo.com/video/'.$m[1] : null;
        } else {
            return null;
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) ? 'https://www.youtube-nocookie.com/embed/'.$id : null;
    }

    /**
     * The storefront menu (§6.4): up to eight entries to the whole catalogue,
     * a collection or a page — the shop's own, never a bare URL.
     *
     * @param  list<mixed>  $input
     * @return list<array<string, mixed>>
     */
    public static function normalizeNavigation(array $input, int $vendorId): array
    {
        $out = [];
        foreach (array_slice(array_values($input), 0, 8) as $raw) {
            $link = is_array($raw) ? self::link($raw, $vendorId, withLabel: true) : null;
            if ($link !== null) {
                $out[] = $link;
            }
        }

        return $out;
    }

    /**
     * SEO fields (§6.7): title, description, a share image from the library.
     *
     * @param  array<string, mixed>  $input
     * @return array{title: ?string, description: ?string, image: ?int}
     */
    public static function normalizeSeo(array $input, int $vendorId): array
    {
        $text = fn (mixed $v, int $max) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;

        return [
            'title' => $text($input['title'] ?? null, 70),
            'description' => $text($input['description'] ?? null, 160),
            'image' => self::ownImages([$input['image'] ?? null], $vendorId, 1)[0] ?? null,
        ];
    }
}
