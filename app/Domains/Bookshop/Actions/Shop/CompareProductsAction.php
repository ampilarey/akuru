<?php

namespace App\Domains\Bookshop\Actions\Shop;

use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Support\ShopPresenter;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

/**
 * Compare products (STATUS §5li): up to four products, kept in this
 * device's session like "recently viewed", side by side — price, shop,
 * stars, stock, category, brand and the book or educational details any of
 * them has. A product no longer for sale drops out of the table.
 */
class CompareProductsAction
{
    public const SESSION_KEY = 'bookshop.compare';

    public const MAX = 4;

    /** @return list<int> */
    public function ids(Session $session): array
    {
        return array_values(array_unique(array_map('intval', (array) $session->get(self::SESSION_KEY, []))));
    }

    /** Add a product, or take it out again. True when it is now in the list. */
    public function toggle(Session $session, string $slug): bool
    {
        $product = ListShopProductsAction::forSale()->where('slug', $slug)->firstOrFail();
        $ids = $this->ids($session);
        if (in_array((int) $product->id, $ids, true)) {
            $session->put(self::SESSION_KEY, array_values(array_diff($ids, [(int) $product->id])));

            return false;
        }
        if (count($ids) >= self::MAX) {
            throw ValidationException::withMessages(['compare' => __('shop.compare_full', ['max' => self::MAX])]);
        }
        $session->put(self::SESSION_KEY, [...$ids, (int) $product->id]);

        return true;
    }

    /**
     * The table: one column per product, and only the rows any of them fills.
     *
     * @return array{columns: list<array<string, mixed>>, rows: list<array{key: string, values: list<?string>}>}
     */
    public function table(Session $session): array
    {
        $ids = $this->ids($session);
        $products = $ids === [] ? collect() : ListShopProductsAction::forSale()->whereIn('id', $ids)
            ->with(['images', 'variants', 'vendor', 'category', 'brand'])->get()->keyBy('id');
        $ordered = collect($ids)->map(fn (int $id) => $products->get($id))->filter()->values();

        $columns = $ordered->map(fn (Product $p) => ShopPresenter::card($p) + [
            'brand' => $p->brand?->name,
            'details' => (array) ($p->details ?? []),
            'weight_grams' => $p->weight_grams,
            'dimensions' => $p->dimensions,
            'has_options' => $p->variants->where('is_active', true)->isNotEmpty(),
            'available' => CustomerListsAction::available($p),
        ])->all();

        $stock = fn (array $c) => match ($c['stock']['state']) {
            'few_left' => __('shop.stock_few_left', ['count' => $c['stock']['count']]),
            'out_of_stock' => __('shop.stock_out_of_stock'),
            'made_to_order' => __('shop.stock_made_to_order', ['days' => $c['stock']['days']]),
            'available' => __('shop.stock_available'),
            default => __('shop.stock_in_stock'),
        };
        $rows = [
            'price' => fn (array $c) => $c['currency'].' '.$c['price'],
            'sold_by' => fn (array $c) => $c['vendor']['name'],
            'rating' => fn (array $c) => $c['rating'] ? $c['rating']['avg'].' ★ ('.$c['rating']['count'].')' : null,
            'availability' => $stock,
            'category' => fn (array $c) => $c['category'],
            'brand' => fn (array $c) => $c['brand'],
        ];
        foreach (['author', 'publisher', 'year', 'pages', 'language', 'age_range', 'grade', 'subject'] as $key) {
            $rows[$key] = fn (array $c) => isset($c['details'][$key]) && $c['details'][$key] !== '' ? (string) $c['details'][$key] : null;
        }
        $rows['weight_grams'] = fn (array $c) => $c['weight_grams'] !== null ? (string) $c['weight_grams'] : null;
        $rows['dimensions'] = fn (array $c) => $c['dimensions'];

        $table = [];
        foreach ($rows as $key => $value) {
            $values = array_map($value, $columns);
            if (array_filter($values, fn ($v) => $v !== null && $v !== '') !== []) {
                $table[] = ['key' => $key, 'values' => $values];
            }
        }

        return ['columns' => $columns, 'rows' => $table];
    }
}
