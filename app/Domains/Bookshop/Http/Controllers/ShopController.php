<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\ListCatalogueOptionsAction;
use App\Domains\Bookshop\Actions\Shop\ApplyToSellAction;
use App\Domains\Bookshop\Actions\Shop\CustomerListsAction;
use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Actions\Shop\PresentShopHomeAction;
use App\Domains\Bookshop\Actions\Shop\PresentShopProductAction;
use App\Domains\Bookshop\Actions\Shop\PresentShopVendorAction;
use App\Domains\Bookshop\Actions\Shop\ProductReviewsAction;
use App\Domains\Bookshop\Actions\Shop\SuggestAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B1b: the public Akuru Online Bookshop. Blade, like
 * the rest of the public site zone (the Digital Library precedent). Nothing
 * here reads or writes per-person data; ordering arrives in B2.
 */
class ShopController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $browsing = array_diff_key($filters, ['sort' => 1]) === [];

        return view('public.shop.index', [
            'home' => $browsing ? app(PresentShopHomeAction::class)->execute() + [
                'recently_viewed' => app(CustomerListsAction::class)->recentlyViewed($request->session()),
                'applications_open' => app(ApplyToSellAction::class)->isOpen(),
            ] : null,
            'products' => app(ListShopProductsAction::class)->execute($filters),
            'filters' => $filters,
            'options' => $this->options(),
            'vendor' => null,
            'heading' => null,
        ]);
    }

    public function category(Request $request, string $slug)
    {
        $options = $this->options();
        $category = collect($options['categories'])->firstWhere('slug', $slug);
        abort_if($category === null, 404);
        $filters = ['category' => $slug] + $this->filters($request);

        return view('public.shop.index', [
            'home' => null,
            'products' => app(ListShopProductsAction::class)->execute($filters),
            'filters' => $filters,
            'options' => $options,
            'vendor' => null,
            'heading' => $category['label'],
        ]);
    }

    public function vendor(Request $request, string $vendor)
    {
        $shop = app(PresentShopVendorAction::class)->execute($vendor);
        abort_if($shop === null, 404);
        $filters = ['vendor' => $shop['slug']] + $this->filters($request);

        return view('public.shop.index', [
            'home' => null,
            'products' => app(ListShopProductsAction::class)->execute($filters, storefront: true),
            'filters' => $filters,
            'options' => $this->options(),
            'vendor' => $shop,
            'heading' => $shop['name'],
        ]);
    }

    /** B5 (§6.4): a page under a vendor's storefront, published with it. */
    public function vendorPage(string $vendor, string $page)
    {
        $shop = app(PresentShopVendorAction::class)->page($vendor, $page);
        abort_if($shop === null, 404);

        return view('public.shop.page', ['vendor' => $shop['vendor'], 'page' => $shop['page']]);
    }

    /** B5 (§5 "Collections"): a vendor's collection, in the vendor's order unless the visitor sorts. */
    public function vendorCollection(Request $request, string $vendor, string $collection)
    {
        $shop = app(PresentShopVendorAction::class)->collection($vendor, $collection);
        abort_if($shop === null, 404);
        $filters = ['vendor' => $shop['vendor']['slug'], 'collection' => $shop['collection']['slug']] + $this->filters($request);

        return view('public.shop.index', [
            'home' => null,
            'products' => app(ListShopProductsAction::class)->execute($filters, storefront: true),
            'filters' => $filters,
            'options' => $this->options(),
            'vendor' => $shop['vendor'],
            'heading' => $shop['collection']['name'],
            'collection' => $shop['collection'],
        ]);
    }

    public function product(Request $request, string $slug)
    {
        $product = app(PresentShopProductAction::class)->execute($slug);
        abort_if($product === null, 404);
        // B7 (§4): reviews, the wishlist and back-in-stock state for the
        // signed-in customer, and this device's recently viewed.
        $lists = app(CustomerListsAction::class);
        $userId = $request->user()?->id;
        $recent = $lists->recentlyViewed($request->session(), $product['id']);
        $lists->rememberViewed($request->session(), $product['id']);

        return view('public.shop.product', ['product' => $product + [
            'reviews' => app(ProductReviewsAction::class)->forProduct($product['id'], $userId),
            'in_wishlist' => $lists->inWishlist($userId, $product['id']),
            'has_alert' => $lists->hasStockAlert($userId, $product['id']),
            'recently_viewed' => $recent,
        ]]);
    }

    /** B7 (§4 "suggestions as you type"): a few products, shops and categories, as JSON. */
    public function suggest(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => 'nullable|string|max:100']);

        return response()->json(app(SuggestAction::class)->execute((string) ($data['q'] ?? '')));
    }

    /** Every listing gets a CSV (conventions): the listing as filtered. */
    public function export(Request $request): StreamedResponse
    {
        $rows = app(ListShopProductsAction::class)->execute($this->filters($request), 2000)->items();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['title', 'shop', 'category', 'price', 'was', 'currency', 'availability', 'url']);
            foreach ($rows as $row) {
                Csv::put($out, [
                    $row['title'], $row['vendor']['name'], $row['category'], $row['price'], $row['compare_at_price'],
                    $row['currency'], $row['stock']['state'], route('public.shop.product', $row['slug']),
                ]);
            }
            fclose($out);
        }, 'akuru-bookstore.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:80',
            'vendor' => 'nullable|string|max:80',
            'collection' => 'nullable|string|max:80',
            'brand' => 'nullable|string|max:80',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'in_stock' => 'nullable|boolean',
            'language' => 'nullable|string|max:40',
            'age' => 'nullable|string|max:20',
            'grade' => 'nullable|string|max:20',
            'sort' => 'nullable|string|in:'.implode(',', ListShopProductsAction::SORTS),
        ]);

        return array_filter($data, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Categories (labelled in the page's language) and brands for the filters.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        $locale = app()->getLocale();
        $catalogue = app(ListCatalogueOptionsAction::class)->execute();

        return [
            'categories' => array_map(fn (array $c) => $c + [
                'label' => ($locale === 'dv' && $c['name_dv']) ? $c['name_dv'] : (($locale === 'ar' && $c['name_ar']) ? $c['name_ar'] : $c['name']),
            ], $catalogue['categories']),
            'brands' => $catalogue['brands'],
            'sorts' => ListShopProductsAction::SORTS,
        ];
    }
}
