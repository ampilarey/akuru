<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\StockAlert;
use App\Domains\Bookshop\Models\WishlistItem;
use App\Domains\Bookshop\Support\ShopPresenter;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

/**
 * The customer's own lists (BOOKSHOP_PLAN §4): the **wishlist** (signed
 * in), **recently viewed** (this device's session, guests too), and
 * **back-in-stock** requests ("out of stock — notify me", told once, in
 * the app). Only products for sale are ever shown back.
 */
class CustomerListsAction
{
    public const SESSION_RECENT = 'bookshop.recently_viewed';

    /* ------------------------------------------------------------ wishlist */

    /** Add or remove; true when it is now on the list. */
    public function toggleWishlist(int $userId, string $productSlug): bool
    {
        $product = ListShopProductsAction::forSale()->where('slug', $productSlug)->firstOrFail();
        $existing = WishlistItem::query()->where('user_id', $userId)->where('product_id', $product->id)->first();
        if ($existing !== null) {
            $existing->delete();

            return false;
        }
        if (WishlistItem::query()->where('user_id', $userId)->count() >= (int) config('bookshop.merchandising.wishlist_max', 200)) {
            throw ValidationException::withMessages(['wishlist' => __('shop.error_wishlist_full')]);
        }
        WishlistItem::query()->create(['user_id' => $userId, 'product_id' => $product->id, 'created_at' => now()]);

        return true;
    }

    public function inWishlist(?int $userId, int $productId): bool
    {
        return $userId !== null && WishlistItem::query()->where('user_id', $userId)->where('product_id', $productId)->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function wishlist(int $userId): array
    {
        $ids = WishlistItem::query()->where('user_id', $userId)->orderByDesc('created_at')->orderByDesc('id')->pluck('product_id')->map(fn ($id) => (int) $id)->all();

        return $this->cards($ids, storefront: true);
    }

    /* ------------------------------------------------------------ recently viewed */

    public function rememberViewed(Session $session, int $productId): void
    {
        $ids = array_values(array_filter((array) $session->get(self::SESSION_RECENT, []), fn ($id) => (int) $id !== $productId));
        array_unshift($ids, $productId);
        $session->put(self::SESSION_RECENT, array_slice(array_map('intval', $ids), 0, (int) config('bookshop.merchandising.recently_viewed', 12)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentlyViewed(Session $session, ?int $exceptId = null, int $limit = 8): array
    {
        $ids = array_values(array_filter(array_map('intval', (array) $session->get(self::SESSION_RECENT, [])), fn (int $id) => $id !== $exceptId));

        return array_slice($this->cards($ids, storefront: true), 0, $limit);
    }

    /* ------------------------------------------------------------ back in stock */

    /** Ask to be told (true) or stop asking (false). Only for something that cannot be bought now. */
    public function toggleStockAlert(int $userId, string $productSlug): bool
    {
        $product = ListShopProductsAction::forSale()->where('slug', $productSlug)->with('variants')->firstOrFail();
        $existing = StockAlert::query()->where('user_id', $userId)->where('product_id', $product->id)->first();
        if ($existing !== null && $existing->notified_at === null) {
            $existing->delete();

            return false;
        }
        if (self::available($product)) {
            throw ValidationException::withMessages(['alert' => __('shop.error_in_stock_already')]);
        }
        StockAlert::query()->updateOrCreate(['user_id' => $userId, 'product_id' => $product->id], ['created_at' => now(), 'notified_at' => null]);

        return true;
    }

    public function hasStockAlert(?int $userId, int $productId): bool
    {
        return $userId !== null && StockAlert::query()->where('user_id', $userId)->where('product_id', $productId)->whereNull('notified_at')->exists();
    }

    /**
     * The product can be bought again: tell everyone waiting, once. Called
     * wherever stock goes up — a vendor's save, a cancellation or return
     * put back on the shelf.
     */
    public function notifyIfBack(int $productId): int
    {
        $product = ListShopProductsAction::forSale()->whereKey($productId)->with(['variants', 'vendor'])->first();
        if ($product === null || ! self::available($product)) {
            return 0;
        }
        $waiting = StockAlert::query()->where('product_id', $productId)->whereNull('notified_at')->get();
        foreach ($waiting as $alert) {
            app(NotifyBookshopUserAction::class)->execute(
                (int) $alert->user_id,
                __('shop.notice_back_in_stock_title'),
                __('shop.notice_back_in_stock_body', ['title' => $product->title, 'vendor' => $product->vendor->name]),
                '/shop/products/'.$product->slug,
            );
        }
        StockAlert::query()->whereIn('id', $waiting->pluck('id'))->update(['notified_at' => now()]);

        return $waiting->count();
    }

    /** Something to buy: stock not counted, on the shelf, made to order, or an active variant with stock. */
    public static function available(Product $product): bool
    {
        if (! $product->track_stock || (int) $product->stock > 0 || (int) $product->lead_days > 0) {
            return true;
        }

        return $product->variants->contains(fn (ProductVariant $v) => $v->is_active && (int) $v->stock > 0);
    }

    /**
     * Cards for these ids, in this order, for sale only.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    private function cards(array $ids, bool $storefront): array
    {
        if ($ids === []) {
            return [];
        }
        $products = ListShopProductsAction::forSale()->whereIn('id', $ids)->with(['images', 'variants', 'vendor', 'category'])->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $products->get($id))->filter()->map(fn (Product $p) => ShopPresenter::card($p))->values()->all();
    }
}
