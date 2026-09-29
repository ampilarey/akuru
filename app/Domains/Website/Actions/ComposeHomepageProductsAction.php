<?php

namespace App\Domains\Website\Actions;

use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Library\Actions\ListLibraryItemsAction;
use Illuminate\Support\Facades\Cache;

/**
 * The home page's product rows (the 2026-09-28 website design, STATUS §5ki):
 * the newest books in the Digital Library and the newest items in the
 * Bookstore, each through its own domain's public listing, so the home page
 * shows exactly what those shelves show — published items, and products for
 * sale from an active shop.
 *
 * Cached for ten minutes like the rest of the home page's lists.
 */
class ComposeHomepageProductsAction
{
    public const BOOKS = 6;

    public const PRODUCTS = 4;

    /**
     * @return array{books: list<array<string, mixed>>, products: list<array<string, mixed>>}
     */
    public function execute(string $locale): array
    {
        return Cache::remember("homepage_products_v1_{$locale}", 600, fn (): array => [
            'books' => array_map(fn (array $item): array => [
                'title' => $item['title'],
                'href' => route('public.library.show', $item['slug']),
                'cover_url' => $item['cover_url'],
                'by' => $item['writer']['display_name'] ?? ($item['authors'][0] ?? null),
                'free' => in_array($item['access_type'], ['free_public', 'free_login'], true),
                'price' => $item['price'],
                'currency' => $item['currency'],
            ], array_slice(app(ListLibraryItemsAction::class)->execute(['sort' => 'newest']), 0, self::BOOKS)),
            'products' => array_map(fn (array $card): array => [
                'title' => $card['title'],
                'href' => route('public.shop.product', $card['slug']),
                'image' => $card['image'],
                'image_alt' => $card['image_alt'],
                'shop' => $card['vendor']['name'],
                'price' => $card['price'],
                'compare_at_price' => $card['on_sale'] ? $card['compare_at_price'] : null,
                'currency' => $card['currency'],
            ], app(ListShopProductsAction::class)->execute(['sort' => 'newest'], self::PRODUCTS)->items()),
        ]);
    }
}
