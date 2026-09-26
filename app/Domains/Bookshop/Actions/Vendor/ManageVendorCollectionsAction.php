<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\VendorCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A shop's collections (BOOKSHOP_PLAN §5 "Collections"): named groups of
 * its own products, hand-picked in an order, or by rule — a tag, a
 * category, or both. Shown by the Collection section and at
 * `/shop/<vendor>/<collection>`. The slug is fixed at creation; a few
 * words the shop's address already uses are not allowed as one.
 */
class ManageVendorCollectionsAction
{
    public const RESERVED_SLUGS = ['p', 'products', 'c', 'cart', 'checkout', 'export'];

    /**
     * @return list<array<string, mixed>>
     */
    public function list(VendorScope $scope): array
    {
        return VendorCollection::query()->where('vendor_id', $scope->vendorId)->with('products:id')->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (VendorCollection $c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'name' => $c->name,
                'name_dv' => $c->name_dv,
                'name_ar' => $c->name_ar,
                'description' => $c->description,
                'kind' => $c->isManual() ? 'manual' : 'rule',
                'product_ids' => $c->products->pluck('id')->map(fn ($id) => (int) $id)->all(),
                'rule' => ((array) ($c->rule ?? [])) + ['tags' => [], 'category_id' => null],
                'is_active' => (bool) $c->is_active,
                'sort_order' => (int) $c->sort_order,
                'for_sale_count' => $c->forSaleQuery()->count(),
            ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(VendorScope $scope, ?int $collectionId, array $data): VendorCollection
    {
        $text = fn (mixed $v, int $max) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;
        $name = $text($data['name'] ?? null, 120);
        if ($name === null) {
            throw ValidationException::withMessages(['name' => __('shop.error_collection_name_required')]);
        }
        $rule = null;
        if (($data['kind'] ?? 'manual') === 'rule') {
            $tags = array_values(array_unique(array_filter(array_map(fn ($t) => mb_substr(trim((string) $t), 0, 40), (array) ($data['rule']['tags'] ?? [])), fn ($t) => $t !== '')));
            $categoryId = is_numeric($data['rule']['category_id'] ?? null) ? (int) $data['rule']['category_id'] : null;
            if ($tags === [] && $categoryId === null) {
                throw ValidationException::withMessages(['rule' => __('shop.error_collection_rule_empty')]);
            }
            $rule = ['tags' => $tags, 'category_id' => $categoryId];
        }
        $productIds = array_values(array_unique(array_map('intval', array_filter((array) ($data['product_ids'] ?? []), 'is_numeric'))));
        $own = Product::query()->where('vendor_id', $scope->vendorId)->whereIn('id', $productIds)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $productIds = array_values(array_filter($productIds, fn (int $id) => in_array($id, $own, true)));

        return DB::transaction(function () use ($scope, $collectionId, $data, $text, $name, $rule, $productIds) {
            if ($collectionId === null) {
                $max = (int) config('bookshop.storefront.max_collections', 20);
                if (VendorCollection::query()->where('vendor_id', $scope->vendorId)->count() >= $max) {
                    throw ValidationException::withMessages(['name' => __('shop.error_too_many_collections', ['max' => $max])]);
                }
                $slug = Str::slug(mb_substr(trim((string) (($data['slug'] ?? '') ?: $name)), 0, 80));
                if ($slug === '' || in_array($slug, self::RESERVED_SLUGS, true) || VendorCollection::query()->where('vendor_id', $scope->vendorId)->where('slug', $slug)->exists()) {
                    throw ValidationException::withMessages(['slug' => __('shop.error_slug_taken', ['slug' => $slug])]);
                }
                $collection = VendorCollection::query()->create([
                    'vendor_id' => $scope->vendorId,
                    'slug' => $slug,
                    'sort_order' => (int) VendorCollection::query()->where('vendor_id', $scope->vendorId)->max('sort_order') + 1,
                    'name' => $name,
                ]);
            } else {
                $collection = VendorCollection::query()->where('vendor_id', $scope->vendorId)->whereKey($collectionId)->firstOrFail();
            }
            $collection->update([
                'name' => $name,
                'name_dv' => $text($data['name_dv'] ?? null, 120),
                'name_ar' => $text($data['name_ar'] ?? null, 120),
                'description' => $text($data['description'] ?? null, 500),
                'rule' => $rule,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
                'sort_order' => is_numeric($data['sort_order'] ?? null) ? max(0, (int) $data['sort_order']) : $collection->sort_order,
            ]);
            $sync = [];
            foreach ($productIds as $i => $id) {
                $sync[$id] = ['sort_order' => $i];
            }
            $collection->products()->sync($rule === null ? $sync : []);

            return $collection->refresh();
        });
    }

    public function delete(VendorScope $scope, int $collectionId): void
    {
        VendorCollection::query()->where('vendor_id', $scope->vendorId)->whereKey($collectionId)->firstOrFail()->delete();
    }
}
