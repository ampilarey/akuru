<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * What the shop says about a product beyond its price (BOOKSHOP_PLAN §6.5
 * "product badges ('New', 'Sale', 'Bestseller', custom text)", §4 "best
 * selling and top rated" sorts, reviews and ratings): one place, so a card,
 * a product page and a sort never disagree.
 */
final class Merchandise
{
    /** @var list<int>|null */
    private static ?array $bestSellers = null;

    /**
     * Units of a product paid for in the best-seller window, as a subquery
     * against `products.id` — for the best-selling sort.
     */
    public static function unitsSold(): Builder
    {
        return OrderItem::query()
            ->selectRaw('coalesce(sum(order_items.quantity), 0)')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereColumn('order_items.product_id', 'products.id')
            ->whereNotNull('orders.paid_at')
            ->where('orders.paid_at', '>=', now()->subDays((int) config('bookshop.storefront.best_seller_days', 90)))
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Expired->value]);
    }

    /**
     * The shop's best sellers right now: the top products by units paid for
     * in the window, with at least the minimum. Cached for ten minutes.
     *
     * @return list<int>
     */
    public static function bestSellerIds(): array
    {
        if (self::$bestSellers !== null) {
            return self::$bestSellers;
        }

        return self::$bestSellers = Cache::remember('bookshop.bestsellers', 600, fn () => OrderItem::query()
            ->selectRaw('order_items.product_id, sum(order_items.quantity) as units')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('order_items.product_id')
            ->whereNotNull('orders.paid_at')
            ->where('orders.paid_at', '>=', now()->subDays((int) config('bookshop.storefront.best_seller_days', 90)))
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value, OrderStatus::Expired->value])
            ->groupBy('order_items.product_id')
            ->havingRaw('sum(order_items.quantity) >= ?', [(int) config('bookshop.merchandising.bestseller_min_units', 2)])
            ->orderByDesc('units')
            ->limit((int) config('bookshop.merchandising.bestseller_top', 10))
            ->pluck('order_items.product_id')->map(fn ($id) => (int) $id)->all());
    }

    /** Forget the cached best sellers (tests, and after a day's sales change them). */
    public static function forget(): void
    {
        self::$bestSellers = null;
        Cache::forget('bookshop.bestsellers');
    }

    /**
     * Up to three badges, the vendor's own words first, then Sale,
     * Bestseller and New.
     *
     * @return list<array{kind: string, label: string}>
     */
    public static function badges(Product $product): array
    {
        $badges = [];
        $custom = ShopPresenter::localized($product, 'badge');
        if (is_string($custom) && trim($custom) !== '') {
            $badges[] = ['kind' => 'custom', 'label' => trim($custom)];
        }
        if ($product->compare_at_price !== null && (float) $product->compare_at_price > (float) $product->price) {
            $badges[] = ['kind' => 'sale', 'label' => __('shop.on_sale')];
        }
        if (in_array((int) $product->id, self::bestSellerIds(), true)) {
            $badges[] = ['kind' => 'bestseller', 'label' => __('shop.badge_bestseller')];
        }
        if ($product->created_at !== null && $product->created_at->gte(now()->subDays((int) config('bookshop.merchandising.new_days', 30)))) {
            $badges[] = ['kind' => 'new', 'label' => __('shop.badge_new')];
        }

        return array_slice($badges, 0, 3);
    }

    /**
     * The stars on a card.
     *
     * @return array{avg: string, count: int}|null
     */
    public static function rating(Product $product): ?array
    {
        return (int) $product->rating_count > 0 && $product->rating_avg !== null
            ? ['avg' => number_format((float) $product->rating_avg, 1, '.', ''), 'count' => (int) $product->rating_count]
            : null;
    }

    /** Recompute a product's average and count from its published reviews. */
    public static function refreshRating(int $productId): void
    {
        $row = ProductReview::query()->where('product_id', $productId)->where('status', ProductReview::PUBLISHED)
            ->selectRaw('avg(rating) as avg_rating, count(*) as n')->first();
        $count = (int) ($row->n ?? 0);
        Product::query()->whereKey($productId)->update([
            'rating_avg' => $count > 0 ? round((float) $row->avg_rating, 2) : null,
            'rating_count' => $count,
        ]);
    }
}
