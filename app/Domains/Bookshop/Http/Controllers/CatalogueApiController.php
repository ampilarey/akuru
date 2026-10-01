<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Shop\ListShopProductsAction;
use App\Domains\Bookshop\Actions\Shop\PresentCatalogueApiAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * STATUS §5ll: the Bookstore's public catalogue as JSON, read-only. Thin:
 * the language, the filters, the page size; the Action decides what is in
 * it. Titles, summaries and category names come in `?lang=en|dv|ar`.
 */
class CatalogueApiController extends Controller
{
    public function products(Request $request, PresentCatalogueApiAction $catalogue): JsonResponse
    {
        $this->language($request);
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:80',
            'shop' => 'nullable|string|max:80',
            'brand' => 'nullable|string|max:80',
            'price_min' => 'nullable|numeric|min:0',
            'price_max' => 'nullable|numeric|min:0',
            'in_stock' => 'nullable|boolean',
            'language' => 'nullable|string|max:40',
            'age' => 'nullable|string|max:20',
            'grade' => 'nullable|string|max:20',
            'deals' => 'nullable|boolean',
            'used' => 'nullable|boolean',
            'sort' => 'nullable|string|in:'.implode(',', ListShopProductsAction::SORTS),
            'per_page' => 'nullable|integer|min:1|max:'.PresentCatalogueApiAction::MAX_PER_PAGE,
        ]);
        // A shop is a "vendor" to the listing; the API says what a customer sees.
        $filters = array_filter(['vendor' => $data['shop'] ?? null] + collect($data)->except(['shop', 'per_page'])->all(), fn ($value) => $value !== null && $value !== '');

        return response()->json($catalogue->products($filters, (int) ($data['per_page'] ?? 24)));
    }

    public function product(Request $request, PresentCatalogueApiAction $catalogue, string $slug): JsonResponse
    {
        $this->language($request);
        $product = $catalogue->product($slug);
        abort_if($product === null, 404);

        return response()->json(['data' => $product]);
    }

    public function shops(PresentCatalogueApiAction $catalogue): JsonResponse
    {
        return response()->json(['data' => $catalogue->shops()]);
    }

    public function categories(Request $request, PresentCatalogueApiAction $catalogue): JsonResponse
    {
        $this->language($request);

        return response()->json(['data' => $catalogue->categories()]);
    }

    private function language(Request $request): void
    {
        $lang = $request->query('lang');
        app()->setLocale(in_array($lang, ['en', 'dv', 'ar'], true) ? $lang : 'en');
    }
}
