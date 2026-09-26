<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCollection;
use App\Domains\Bookshop\Models\VendorDeliveryMethod;
use App\Domains\Bookshop\Models\VendorPage;
use App\Domains\Bookshop\Models\VendorStorefrontImage;
use App\Domains\Bookshop\Support\Identity;
use App\Domains\Bookshop\Support\SectionTypes;
use App\Domains\Bookshop\Support\ShopPresenter;
use App\Domains\Media\Actions\ResolvePublicImageVariantAction;

/**
 * Sections as the page draws them (BOOKSHOP_PLAN §6.3, §10 "Storefront
 * rendering"): the ones visible now (hidden and out-of-schedule ones are
 * dropped unless this is the vendor's draft preview), each turned from
 * stored settings into what the view needs — text in the visitor's
 * language, library ids into image URLs, product ids into cards, a
 * collection into its cards and address, the vendor's categories into
 * tiles, best sellers from paid order lines, a video URL into its embed,
 * coordinates into a map embed. The view only prints; it never looks
 * anything up.
 */
class RenderSectionsAction
{
    public const IMAGE_WIDTH = 1200;

    /**
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    public function execute(Vendor $vendor, array $sections, bool $draft = false): array
    {
        $out = [];
        foreach ($sections as $section) {
            if (! is_array($section) || ! array_key_exists($section['type'] ?? '', SectionTypes::TYPES) || ! SectionTypes::isVisible($section, $draft)) {
                continue;
            }
            $settings = (array) ($section['settings'] ?? []);
            $data = match ($section['type']) {
                'hero' => $this->hero($vendor, $settings),
                'announcement' => $this->announcement($vendor, $settings, $draft),
                'featured_products' => ['cards' => $this->cards($this->ownProducts($vendor)->whereIn('id', (array) ($settings['products'] ?? [])), (array) ($settings['products'] ?? [])), 'layout' => $settings['layout'] ?? 'grid'],
                'collection' => $this->collection($vendor, $settings),
                'category_tiles' => ['tiles' => $this->tiles($vendor)],
                'new_arrivals' => ['cards' => $this->cards($this->ownProducts($vendor)->orderByDesc('created_at')->orderByDesc('id')->limit((int) ($settings['count'] ?? 8)))],
                'best_sellers' => ['cards' => $this->bestSellers($vendor, (int) ($settings['count'] ?? 8))],
                'text_image' => ['body' => $this->text($settings, 'body'), 'image' => $this->image($settings['image'] ?? null, self::IMAGE_WIDTH), 'image_alt' => $this->alt($vendor, $settings['image'] ?? null), 'image_side' => $settings['image_side'] ?? 'end'],
                'gallery' => ['images' => $this->gallery($vendor, (array) ($settings['images'] ?? []))],
                'testimonials', 'faq' => ['items' => array_values((array) ($settings['items'] ?? []))],
                'delivery_returns' => $this->deliveryReturns($vendor, $settings),
                'contact_map' => $this->contactMap($vendor, $settings, $draft),
                'video' => ['embed' => SectionTypes::videoEmbed($settings['url'] ?? null), 'url' => $settings['url'] ?? null],
                default => [],
            };
            // An empty shelf is not a shop window: a section with nothing in it
            // is left out of the public page (the vendor's preview keeps it,
            // with its "nothing yet" note).
            if ($data === null || (! $draft && $this->isEmpty((string) $section['type'], $data))) {
                continue;
            }
            $out[] = [
                'id' => (string) ($section['id'] ?? ''),
                'type' => (string) $section['type'],
                'heading' => $this->text($settings, 'heading'),
                'mobile_order' => is_numeric($section['mobile_order'] ?? null) ? (int) $section['mobile_order'] : null,
                'draft_hidden' => $draft && ! SectionTypes::isVisible($section),
            ] + $data;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isEmpty(string $type, array $data): bool
    {
        return match ($type) {
            'featured_products', 'new_arrivals', 'best_sellers', 'collection' => ($data['cards'] ?? []) === [],
            'category_tiles' => ($data['tiles'] ?? []) === [],
            'gallery' => ($data['images'] ?? []) === [],
            'testimonials', 'faq' => ($data['items'] ?? []) === [],
            'video' => ($data['embed'] ?? null) === null,
            default => false,
        };
    }

    /** The public address a section link points at, or null when its target is gone. */
    public function url(Vendor $vendor, ?array $link, bool $draft = false): ?string
    {
        if ($link === null) {
            return null;
        }
        $target = (string) ($link['target'] ?? '');

        return match ($link['kind'] ?? null) {
            'all' => route('public.shop.vendor', $vendor->slug),
            'collection' => VendorCollection::query()->where('vendor_id', $vendor->id)->where('slug', $target)->where('is_active', true)->exists() ? route('public.shop.vendor.collection', [$vendor->slug, $target]) : null,
            'page' => VendorPage::query()->where('vendor_id', $vendor->id)->where('slug', $target)->when(! $draft, fn ($q) => $q->whereNotNull('published_at'))->exists() ? route('public.shop.vendor.page', [$vendor->slug, $target]) : null,
            'product' => ListShopProductsAction::forSale()->where('vendor_id', $vendor->id)->where('slug', $target)->exists() ? route('public.shop.product', $target) : null,
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $links
     * @return list<array{label: string, url: string}>
     */
    public function links(Vendor $vendor, array $links, bool $draft = false): array
    {
        $out = [];
        foreach ($links as $link) {
            $url = is_array($link) ? $this->url($vendor, $link, $draft) : null;
            if ($url !== null) {
                $out[] = ['label' => $this->text($link, 'label') ?? '', 'url' => $url];
            }
        }

        return array_values(array_filter($out, fn ($l) => $l['label'] !== ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function hero(Vendor $vendor, array $settings): array
    {
        return [
            'subheading' => $this->text($settings, 'subheading'),
            'images' => array_values(array_filter(array_map(fn ($id) => $this->image($id, ResolveStorefrontAction::BANNER_WIDTH), (array) ($settings['images'] ?? [])))),
            'buttons' => $this->links($vendor, (array) ($settings['buttons'] ?? []), true),
            'align' => $settings['align'] ?? 'start',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function announcement(Vendor $vendor, array $settings, bool $draft): ?array
    {
        $ends = $settings['ends_at'] ?? null;
        if (! $draft && is_string($ends) && $ends < now()->toDateString()) {
            return null;
        }
        $text = $this->text($settings, 'text');
        if ($text === null) {
            return null;
        }

        return ['text' => $text, 'ends_at' => $ends, 'url' => $this->url($vendor, is_array($settings['link'] ?? null) ? $settings['link'] : null, $draft)];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function collection(Vendor $vendor, array $settings): ?array
    {
        $collection = VendorCollection::query()->where('vendor_id', $vendor->id)->whereKey((int) ($settings['collection'] ?? 0))->where('is_active', true)->first();
        if ($collection === null) {
            return null;
        }

        return [
            'collection' => ['name' => $collection->localizedName(), 'url' => route('public.shop.vendor.collection', [$vendor->slug, $collection->slug]), 'description' => $collection->description],
            'cards' => $this->cards($collection->forSaleQuery()->limit((int) ($settings['count'] ?? 8))),
            'see_all' => (bool) ($settings['see_all'] ?? true),
        ];
    }

    /**
     * The vendor's categories with something for sale, each with a count and
     * the newest product's photo as its picture.
     *
     * @return list<array<string, mixed>>
     */
    private function tiles(Vendor $vendor): array
    {
        $counts = $this->ownProducts($vendor)->selectRaw('product_category_id, count(*) as aggregate')->whereNotNull('product_category_id')->groupBy('product_category_id')->pluck('aggregate', 'product_category_id');
        $locale = app()->getLocale();
        $tiles = [];
        foreach (ProductCategory::query()->where('is_active', true)->whereIn('id', $counts->keys()->all())->orderBy('sort_order')->orderBy('name')->get() as $category) {
            $sample = $this->ownProducts($vendor)->where('product_category_id', $category->id)->with('images')->orderByDesc('created_at')->first();
            $first = $sample?->images->first();
            $tiles[] = [
                'name' => ($locale === 'dv' && $category->name_dv) ? $category->name_dv : (($locale === 'ar' && $category->name_ar) ? $category->name_ar : $category->name),
                'count' => (int) $counts->get($category->id, 0),
                'url' => route('public.shop.vendor', $vendor->slug).'?category='.$category->slug,
                'image' => $first !== null ? app(ResolvePublicImageVariantAction::class)->execute((int) $first->media_file_id, ShopPresenter::CARD_WIDTH) : null,
            ];
        }

        return $tiles;
    }

    /**
     * Paid order lines of the last N days (config `best_seller_days`), most
     * units first. Cancelled and expired orders do not count.
     *
     * @return list<array<string, mixed>>
     */
    private function bestSellers(Vendor $vendor, int $count): array
    {
        $since = now()->subDays((int) config('bookshop.storefront.best_seller_days', 90));
        $ids = OrderItem::query()
            ->selectRaw('order_items.product_id, sum(order_items.quantity) as units')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.vendor_id', $vendor->id)
            ->whereNotNull('orders.paid_at')
            ->where('orders.paid_at', '>=', $since)
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Expired->value])
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id')
            ->orderByDesc('units')
            ->limit($count)
            ->pluck('order_items.product_id')
            ->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return [];
        }

        return $this->cards($this->ownProducts($vendor)->whereIn('id', $ids), $ids);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function gallery(Vendor $vendor, array $ids): array
    {
        $images = app(ResolvePublicImageVariantAction::class);
        $alts = VendorStorefrontImage::query()->where('vendor_id', $vendor->id)->whereIn('media_file_id', $ids)->pluck('alt', 'media_file_id');
        $out = [];
        foreach ($ids as $id) {
            $large = $images->execute((int) $id, self::IMAGE_WIDTH);
            if ($large !== null) {
                $out[] = ['card' => $images->execute((int) $id, ShopPresenter::CARD_WIDTH), 'large' => $large, 'alt' => $alts->get((int) $id) ?? ''];
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryReturns(Vendor $vendor, array $settings): array
    {
        $locale = app()->getLocale();
        $own = $vendor->deliveryMethods()->where('is_active', true)->get();
        $methods = $own->isNotEmpty()
            ? $own->map(fn (VendorDeliveryMethod $m) => [
                'name' => ($locale === 'dv' && $m->name_dv) ? $m->name_dv : (($locale === 'ar' && $m->name_ar) ? $m->name_ar : $m->name),
                'fee' => number_format((float) $m->fee, 2, '.', ''),
                'free_over' => $m->free_over !== null ? number_format((float) $m->free_over, 2, '.', '') : null,
                'carrier_paid' => (bool) $m->carrier_paid_on_arrival,
                'handling_days' => (int) $m->handling_days,
                'note' => $m->note,
            ])->values()->all()
            : array_map(fn (array $t) => [
                'name' => (string) $t['name'],
                'fee' => number_format((float) ($t['fee'] ?? 0), 2, '.', ''),
                'free_over' => isset($t['free_over']) ? number_format((float) $t['free_over'], 2, '.', '') : null,
                'carrier_paid' => (bool) ($t['carrier_paid_on_arrival'] ?? false),
                'handling_days' => (int) ($t['handling_days'] ?? 1),
                'note' => $t['note'] ?? null,
            ], (array) config('bookshop.delivery_template', []));

        return [
            'body' => $this->text($settings, 'body'),
            'methods' => $methods,
            'return_window_days' => $vendor->returnWindowDays(),
            'return_conditions' => $vendor->return_conditions,
            'currency' => config('bookshop.currency', 'MVR'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contactMap(Vendor $vendor, array $settings, bool $draft): array
    {
        $identity = ((array) ($draft ? $vendor->storefront?->draft_identity : $vendor->storefront?->published_identity)) + Identity::default();
        $lat = $settings['lat'] ?? null;
        $lng = $settings['lng'] ?? null;
        $map = null;
        if (is_numeric($lat) && is_numeric($lng)) {
            $zoom = (int) ($settings['zoom'] ?? 15);
            $span = 360 / (2 ** $zoom) * 1.2;
            $bbox = implode(',', [round($lng - $span, 6), round($lat - $span / 2, 6), round($lng + $span, 6), round($lat + $span / 2, 6)]);
            $map = [
                'embed' => 'https://www.openstreetmap.org/export/embed.html?bbox='.$bbox.'&layer=mapnik&marker='.$lat.'%2C'.$lng,
                'link' => 'https://www.openstreetmap.org/?mlat='.$lat.'&mlon='.$lng.'#map='.$zoom.'/'.$lat.'/'.$lng,
            ];
        }

        return ['contact' => array_filter((array) $identity['contact']), 'hours' => $identity['hours'], 'map' => $map];
    }

    private function ownProducts(Vendor $vendor)
    {
        return ListShopProductsAction::forSale()->where('vendor_id', $vendor->id)->with(['images', 'variants', 'vendor', 'category']);
    }

    /**
     * @param  list<int>  $order  ids in the order to show them, when hand-picked
     * @return list<array<string, mixed>>
     */
    private function cards($query, array $order = []): array
    {
        $products = $query->get();
        if ($order !== []) {
            $products = $products->sortBy(fn (Product $p) => array_search($p->id, array_map('intval', $order), true))->values();
        }

        return $products->map(fn (Product $p) => ShopPresenter::card($p))->values()->all();
    }

    private function image(mixed $mediaId, int $width): ?string
    {
        return is_numeric($mediaId) ? app(ResolvePublicImageVariantAction::class)->execute((int) $mediaId, $width) : null;
    }

    private function alt(Vendor $vendor, mixed $mediaId): string
    {
        return is_numeric($mediaId) ? (string) (VendorStorefrontImage::query()->where('vendor_id', $vendor->id)->where('media_file_id', (int) $mediaId)->value('alt') ?? '') : '';
    }

    /** The field in the visitor's language when the vendor gave one, else the English one. */
    private function text(array $settings, string $field): ?string
    {
        $locale = app()->getLocale();
        $value = in_array($locale, ['dv', 'ar'], true) ? ($settings[$field.'_'.$locale] ?? null) : null;
        $value = is_string($value) && $value !== '' ? $value : ($settings[$field] ?? null);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
