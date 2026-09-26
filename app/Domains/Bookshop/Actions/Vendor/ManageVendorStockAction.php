<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\Shop\CustomerListsAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Models\Product;
use App\Domains\Bookshop\Models\ProductVariant;
use App\Domains\Bookshop\Models\StockMovement;
use App\Domains\Bookshop\Support\LowStock;
use App\Domains\Bookshop\Support\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The shop's stock page (BOOKSHOP_PLAN §5, slice B8): the stock log, what
 * is low or sold out, and stock received or counted by hand — each a line
 * in the log with who did it. Only this shop's products, ever.
 */
class ManageVendorStockAction
{
    /**
     * @param  array{q?: ?string, kind?: ?string, from?: ?string, to?: ?string, product?: ?int}  $filters
     * @return array{rows: list<array<string, mixed>>, total: int, page: int, last_page: int}
     */
    public function movements(VendorScope $scope, array $filters = [], int $page = 1, ?int $perPage = null): array
    {
        $perPage ??= (int) config('bookshop.operations.movements_per_page', 100);
        $query = $this->query($scope, $filters);
        $total = (clone $query)->count();
        $rows = $query->with(['product:id,title,sku,slug', 'variant:id,name,sku', 'order:id,number'])
            ->orderByDesc('id')->forPage(max(1, $page), $perPage)->get();

        $names = $this->names($rows->pluck('user_id')->filter()->unique()->all());

        return [
            'rows' => $rows->map(fn (StockMovement $m) => self::row($m, $names))->values()->all(),
            'total' => $total,
            'page' => max(1, $page),
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Every line the filters match, for the CSV.
     *
     * @param  array<string, mixed>  $filters
     * @return iterable<array<string, mixed>>
     */
    public function allMovements(VendorScope $scope, array $filters = []): iterable
    {
        $max = (int) config('bookshop.operations.export_max_rows', 20000);
        $rows = $this->query($scope, $filters)->with(['product:id,title,sku,slug', 'variant:id,name,sku', 'order:id,number'])
            ->orderByDesc('id')->limit($max)->get();
        $names = $this->names($rows->pluck('user_id')->filter()->unique()->all());
        foreach ($rows as $m) {
            yield self::row($m, $names);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lowStock(VendorScope $scope): array
    {
        return LowStock::rows($scope->vendorId);
    }

    /**
     * Stock received (`in`, adds) or counted (`count`, sets the number and
     * logs the difference as an adjustment), or a correction (`adjustment`,
     * adds or takes away — breakage, a gift, a miscount).
     */
    public function adjust(VendorScope $scope, int $productId, ?int $variantId, string $mode, int $quantity, ?string $note): StockMovement
    {
        if (! in_array($mode, ['in', 'count', 'adjustment'], true)) {
            throw ValidationException::withMessages(['mode' => __('shop.error_stock_mode')]);
        }
        $movement = DB::transaction(function () use ($scope, $productId, $variantId, $mode, $quantity, $note) {
            $product = Product::query()->where('vendor_id', $scope->vendorId)->whereKey($productId)->lockForUpdate()->firstOrFail();
            if (! $product->track_stock) {
                throw ValidationException::withMessages(['product_id' => __('shop.error_stock_not_counted')]);
            }
            $variant = null;
            if ($variantId !== null) {
                $variant = ProductVariant::query()->where('product_id', $product->id)->whereKey($variantId)->lockForUpdate()->firstOrFail();
            } elseif ($product->variants()->exists()) {
                throw ValidationException::withMessages(['variant_id' => __('shop.error_stock_pick_variant')]);
            }
            $row = $variant ?? $product;
            $current = (int) $row->stock;
            $delta = match ($mode) {
                'in' => abs($quantity),
                'count' => $quantity - $current,
                default => $quantity,
            };
            if ($mode === 'count' && $quantity < 0) {
                throw ValidationException::withMessages(['quantity' => __('shop.error_stock_negative')]);
            }
            if ($delta === 0) {
                throw ValidationException::withMessages(['quantity' => __('shop.error_stock_no_change')]);
            }
            if ($current + $delta < 0) {
                throw ValidationException::withMessages(['quantity' => __('shop.error_stock_below_zero', ['stock' => $current])]);
            }
            $row->forceFill(['stock' => $current + $delta])->save();
            StockLedger::record($product, $variant, $delta, $mode === 'in' ? 'in' : 'adjustment', $scope->userId, null,
                trim((string) $note) !== '' ? trim((string) $note) : ($mode === 'count' ? __('shop.stock_counted_note', ['count' => $quantity]) : null));

            return StockMovement::query()->where('product_id', $product->id)->latest('id')->firstOrFail();
        });
        app(CustomerListsAction::class)->notifyIfBack($productId);

        return $movement;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function query(VendorScope $scope, array $filters)
    {
        $q = trim((string) ($filters['q'] ?? ''));

        return StockMovement::query()->where('vendor_id', $scope->vendorId)
            ->when(! empty($filters['product']), fn ($w) => $w->where('product_id', (int) $filters['product']))
            ->when(in_array($filters['kind'] ?? null, StockMovement::KINDS, true), fn ($w) => $w->where('kind', $filters['kind']))
            ->when(! empty($filters['from']), fn ($w) => $w->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when(! empty($filters['to']), fn ($w) => $w->where('created_at', '<=', $filters['to'].' 23:59:59'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->whereHas('product', fn ($p) => $p->where('title', 'like', '%'.$q.'%')->orWhere('sku', 'like', '%'.$q.'%'))
                ->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', '%'.$q.'%'))));
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $userModel = config('auth.providers.users.model');

        return $userModel::query()->whereIn('id', $ids)->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
    }

    /**
     * @param  array<int, string>  $names
     * @return array<string, mixed>
     */
    private static function row(StockMovement $m, array $names): array
    {
        return [
            'id' => $m->id,
            'at' => $m->created_at?->toDateTimeString(),
            'kind' => $m->kind,
            'product' => $m->product?->title,
            'slug' => $m->product?->slug,
            'variant' => $m->variant?->name,
            'sku' => $m->variant?->sku ?? $m->product?->sku,
            'quantity' => $m->quantity,
            'stock_after' => $m->stock_after,
            'order' => $m->order?->number,
            'note' => $m->note,
            'by' => $m->user_id !== null ? ($names[$m->user_id] ?? null) : null,
        ];
    }
}
