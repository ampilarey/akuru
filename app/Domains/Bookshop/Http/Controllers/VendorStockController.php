<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Vendor\ImportVendorProductsAction;
use App\Domains\Bookshop\Actions\Vendor\ListVendorProductsAction;
use App\Domains\Bookshop\Actions\Vendor\ManageVendorStockAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
use App\Domains\Bookshop\Models\StockMovement;
use App\Domains\Bookshop\Support\ProductSheet;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B8: the shop's stock page (`/vendor/stock`) — the
 * stock log, what is low or sold out, stock received or counted, and the
 * product sheet in and out. Thin: which shop, which rows and every rule are
 * in the Actions, through the `VendorScope`.
 */
class VendorStockController extends Controller
{
    use AuthorizesVendor;

    public function index(Request $request): Response
    {
        $scope = $this->authorizeVendor($request);
        $filters = $this->filters($request);
        $stock = app(ManageVendorStockAction::class);
        $token = (string) $request->query('import', '');
        $import = $token !== '' ? app(ImportVendorProductsAction::class)->show($scope, $token) : null;

        return Inertia::render('Bookshop/VendorStock', [
            't' => trans('shop'),
            'vendor' => ['name' => $scope->vendorName, 'slug' => $scope->vendorSlug, 'role' => $scope->role->value],
            'low_stock' => $stock->lowStock($scope),
            'movements' => $stock->movements($scope, $filters, (int) $request->query('page', 1)),
            'kinds' => StockMovement::KINDS,
            'filters' => $filters,
            'products' => array_map(fn (array $p) => ['id' => $p['id'], 'title' => $p['title'], 'sku' => $p['sku'], 'stock' => $p['stock'], 'track_stock' => $p['track_stock'],
                'variants' => array_map(fn (array $v) => ['id' => $v['id'], 'name' => $v['name'], 'sku' => $v['sku'], 'stock' => $v['stock']], $p['variants'])],
                app(ListVendorProductsAction::class)->execute($scope, [], 5000)),
            'import' => $import,
            'import_expired' => $token !== '' && $import === null,
            'import_result' => $request->session()->get('import_result'),
            'columns' => ProductSheet::COLUMNS,
            'limits' => ['rows' => (int) config('bookshop.operations.import_max_rows'), 'kilobytes' => (int) config('bookshop.operations.import_max_kilobytes')],
        ]);
    }

    public function adjust(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'product_id' => 'required|integer',
            'variant_id' => 'nullable|integer',
            'mode' => 'required|string|in:in,count,adjustment',
            'quantity' => 'required|integer|min:-100000|max:100000',
            'note' => 'nullable|string|max:255',
        ]);

        app(ManageVendorStockAction::class)->adjust($scope, (int) $data['product_id'], isset($data['variant_id']) ? (int) $data['variant_id'] : null, $data['mode'], (int) $data['quantity'], $data['note'] ?? null);

        return back()->with('success', __('shop.stock_saved_flash'));
    }

    public function preview(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:'.(int) config('bookshop.operations.import_max_kilobytes', 2048)]);

        $token = app(ImportVendorProductsAction::class)->preview($scope, $request->file('file'));

        return redirect()->to(route('vendor.stock.index', ['import' => $token]).'#import');
    }

    public function apply(Request $request, string $token): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);

        $result = app(ImportVendorProductsAction::class)->apply($scope, $token);

        return redirect()->route('vendor.stock.index')->with('success', __('shop.import_done_flash', [
            'created' => $result['created'], 'updated' => $result['updated'], 'variants' => $result['variants'], 'skipped' => $result['skipped'] + count($result['failed']),
        ]))->with('import_result', $result);
    }

    /** The product sheet with no rows: the columns to fill in. */
    public function template(Request $request): StreamedResponse
    {
        $this->authorizeVendor($request);

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ProductSheet::COLUMNS);
            fclose($out);
        }, 'product-sheet-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Every listing gets a CSV (conventions): the stock log, as filtered. */
    public function exportMovements(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(ManageVendorStockAction::class)->allMovements($scope, $this->filters($request));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['date', 'kind', 'product', 'variant', 'sku', 'quantity', 'stock_after', 'order', 'note', 'by']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['at'], $r['kind'], $r['product'], $r['variant'], $r['sku'], $r['quantity'], $r['stock_after'], $r['order'], $r['note'], $r['by']]);
            }
            fclose($out);
        }, $scope->vendorSlug.'-stock-log.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Every listing gets a CSV (conventions): low and sold out. */
    public function exportLowStock(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(ManageVendorStockAction::class)->lowStock($scope);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['product', 'variant', 'sku', 'stock', 'low_stock_at', 'state', 'status']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['title'], $r['variant'], $r['sku'], $r['stock'], $r['low_stock_at'], $r['state'], $r['status']]);
            }
            fclose($out);
        }, $scope->vendorSlug.'-low-stock.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{q: ?string, kind: ?string, from: ?string, to: ?string, product: ?int}
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:100',
            'kind' => 'nullable|string|in:'.implode(',', StockMovement::KINDS),
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
            'product' => 'nullable|integer',
        ]);

        return ['q' => $data['q'] ?? null, 'kind' => $data['kind'] ?? null, 'from' => $data['from'] ?? null, 'to' => $data['to'] ?? null, 'product' => isset($data['product']) ? (int) $data['product'] : null];
    }
}
