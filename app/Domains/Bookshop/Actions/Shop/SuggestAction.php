<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Enums\ProductVisibility;
use App\Domains\Bookshop\Enums\VendorStatus;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductCategory;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Support\ShopPresenter;

/**
 * "Suggestions as you type" (BOOKSHOP_PLAN §4 "Search"): a few products,
 * shops and categories for what has been typed so far, each with its
 * address. Shop-wide products only (a vendor's page-only products are for
 * its own page), from two characters, never more than a handful.
 */
class SuggestAction
{
    /**
     * @return array{products: list<array<string, mixed>>, vendors: list<array<string, string>>, categories: list<array<string, string>>}
     */
    public function execute(string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return ['products' => [], 'vendors' => [], 'categories' => []];
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
        $locale = app()->getLocale();

        $products = ListShopProductsAction::forSale()->where('visibility', ProductVisibility::Shop->value)
            ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('title_dv', 'like', $like)->orWhere('title_ar', 'like', $like)
                ->orWhere('sku', 'like', $like)->orWhere('barcode', 'like', $like)->orWhere('tags', 'like', $like)->orWhere('details->author', 'like', $like))
            ->with(['images', 'variants', 'vendor', 'category'])
            ->orderByRaw('title like ? desc', [str_replace(['%', '_'], ['\\%', '\\_'], $q).'%'])->orderByDesc('created_at')
            ->limit(6)->get()
            ->map(function (Product $p) {
                $card = ShopPresenter::card($p);

                return ['title' => $card['title'], 'url' => route('public.shop.product', $p->slug), 'vendor' => $card['vendor']['name'], 'price' => $card['currency'].' '.$card['price'], 'image' => $card['image']];
            })->values()->all();

        $vendors = Vendor::query()->where('status', VendorStatus::Active->value)->where('name', 'like', $like)->orderBy('name')->limit(3)->get()
            ->map(fn (Vendor $v) => ['name' => $v->name, 'url' => route('public.shop.vendor', $v->slug)])->values()->all();

        $categories = ProductCategory::query()->where('is_active', true)
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('name_dv', 'like', $like)->orWhere('name_ar', 'like', $like))
            ->orderBy('name')->limit(3)->get()
            ->map(fn (ProductCategory $c) => [
                'name' => ($locale === 'dv' && $c->name_dv) ? $c->name_dv : (($locale === 'ar' && $c->name_ar) ? $c->name_ar : $c->name),
                'url' => route('public.shop.category', $c->slug),
            ])->values()->all();

        return ['products' => $products, 'vendors' => $vendors, 'categories' => $categories];
    }
}
