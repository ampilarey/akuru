<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\Shop\CustomerListsAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Support\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Working through many products at once (BOOKSHOP_PLAN §5 "Bulk", slice
 * B8): put a selection on sale, back to draft or into the archive; and
 * duplicate a product as a draft to make a similar one quickly. Only this
 * shop's products; ids from anyone else's shop are simply not found.
 */
class BulkVendorProductsAction
{
    public const STATUSES = ['active', 'draft', 'archived'];

    /**
     * @param  list<int>  $ids
     */
    public function setStatus(VendorScope $scope, array $ids, string $status): int
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('shop.error_bulk_status')]);
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $changed = Product::query()->where('vendor_id', $scope->vendorId)->whereIn('id', $ids)->where('status', '!=', $status)
            ->update(['status' => $status, 'updated_by' => $scope->userId, 'updated_at' => now()]);
        if ($status === 'active') {
            foreach ($ids as $id) {
                app(CustomerListsAction::class)->notifyIfBack($id);
            }
        }

        return $changed;
    }

    /**
     * A draft copy: the words, prices, category, details and variants, with
     * no SKU (SKUs are the shop's own and unique), no stock and no photos
     * (a photo belongs to one product; add the copy's on the form).
     */
    public function duplicate(VendorScope $scope, int $productId): Product
    {
        return DB::transaction(function () use ($scope, $productId) {
            $source = Product::query()->where('vendor_id', $scope->vendorId)->with('variants')->findOrFail($productId);
            $copy = $source->replicate(['slug', 'sku', 'barcode', 'stock', 'status', 'rating_avg', 'rating_count', 'low_stock_notified_at', 'created_by', 'updated_by']);
            $copy->title = Str::limit(__('shop.copy_of', ['title' => $source->title]), 250, '');
            $copy->slug = $this->slug($source->slug);
            $copy->sku = null;
            $copy->barcode = null;
            $copy->stock = 0;
            $copy->status = 'draft';
            $copy->rating_count = 0;
            $copy->created_by = $scope->userId;
            $copy->updated_by = $scope->userId;
            $copy->save();
            foreach ($source->variants as $variant) {
                /** @var ProductVariant $variant */
                ProductVariant::query()->create([
                    'product_id' => $copy->id, 'name' => $variant->name, 'sku' => null, 'price' => $variant->price,
                    'stock' => 0, 'sort_order' => $variant->sort_order, 'is_active' => $variant->is_active,
                ]);
            }
            StockLedger::checkLow($copy);

            return $copy;
        });
    }

    private function slug(string $base): string
    {
        $root = Str::limit($base, 100, '').'-copy';
        $slug = $root;
        for ($n = 2; Product::query()->where('slug', $slug)->exists(); $n++) {
            $slug = $root.'-'.$n;
        }

        return $slug;
    }
}
