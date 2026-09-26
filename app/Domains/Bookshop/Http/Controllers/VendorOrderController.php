<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Vendor\ConfirmVendorSlipAction;
use App\Domains\Bookshop\Actions\Vendor\FulfilVendorOrderAction;
use App\Domains\Bookshop\Actions\Vendor\ListVendorOrdersAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B3: the shop's order queue (`/vendor/orders`). New
 * UI, so Inertia. Thin: which orders, which steps, what goes back — all in
 * the Actions that take the `VendorScope`.
 */
class VendorOrderController extends Controller
{
    use AuthorizesVendor;

    public function index(Request $request): Response
    {
        $scope = $this->authorizeVendor($request);
        $filters = $request->validate([
            'status' => 'nullable|string|in:returns,pending_payment,paid,cash_due,needs_attention,processing,ready,dispatched,delivered,cancelled',
            'q' => 'nullable|string|max:40',
        ]);
        $list = app(ListVendorOrdersAction::class)->execute($scope, $filters);

        return Inertia::render('Bookshop/VendorOrders', [
            't' => trans('shop'),
            'vendor' => ['name' => $scope->vendorName, 'slug' => $scope->vendorSlug, 'role' => $scope->role->value],
            'orders' => $list['orders'],
            'counts' => $list['counts'],
            'filters' => $filters + ['status' => null, 'q' => null],
        ]);
    }

    public function advance(Request $request, int $order): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'to' => 'required|string|in:processing,ready,dispatched,delivered',
            'carrier' => 'nullable|string|max:120',
            'tracking_note' => 'nullable|string|max:500',
            'cash_received' => 'nullable|boolean',
        ]);

        app(FulfilVendorOrderAction::class)->advance($scope, $order, $data['to'], $data);

        return back()->with('success', __('shop.order_updated_flash'));
    }

    public function cancel(Request $request, int $order): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['reason' => 'required|string|max:500']);

        app(FulfilVendorOrderAction::class)->cancel($scope, $order, $data['reason']);

        return back()->with('success', __('shop.order_cancelled_flash'));
    }

    public function message(Request $request, int $order): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['body' => 'required|string|max:5000']);

        app(FulfilVendorOrderAction::class)->message($scope, $order, $data['body']);

        return back()->with('success', __('shop.message_sent_flash'));
    }

    public function decideReturn(Request $request, int $return): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'decision' => 'required|string|in:accept,decline',
            'restock' => 'nullable|boolean',
            'note' => 'nullable|string|max:500',
        ]);

        app(FulfilVendorOrderAction::class)->decideReturn($scope, $return, $data['decision'] === 'accept', (bool) ($data['restock'] ?? false), $data['note'] ?? null);

        return back()->with('success', __($data['decision'] === 'accept' ? 'shop.return_accepted_flash' : 'shop.return_declined_flash'));
    }

    public function decideSlip(Request $request, int $slip): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'decision' => 'required|string|in:confirm,reject',
            'note' => 'nullable|string|max:500|required_if:decision,reject',
        ]);

        app(ConfirmVendorSlipAction::class)->decide($scope, $slip, $data['decision'] === 'confirm', $data['note'] ?? null);

        return back()->with('success', __($data['decision'] === 'confirm' ? 'shop.slip_confirmed_flash' : 'shop.slip_rejected_flash'));
    }

    public function slip(Request $request, int $slip)
    {
        $scope = $this->authorizeVendor($request);
        $read = app(ConfirmVendorSlipAction::class)->read($scope, $slip);
        abort_if($read === null, 404);

        return response($read['contents'], 200, ['Content-Type' => $read['mime'], 'Content-Disposition' => 'inline; filename="'.addslashes($read['original_name']).'"']);
    }

    /** The packing slip and delivery label, on one printable page. */
    public function print(Request $request, int $order): Response
    {
        $scope = $this->authorizeVendor($request);
        $row = app(ListVendorOrdersAction::class)->one($scope, $order);
        abort_if($row === null, 404);

        return Inertia::render('Bookshop/VendorOrderPrint', ['t' => trans('shop'), 'order' => $row]);
    }

    /** Every listing gets a CSV (conventions); contacts masked as on screen (decision 15). */
    public function export(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(ListVendorOrdersAction::class)->execute($scope, $this->exportFilters($request), (int) config('bookshop.operations.export_max_rows', 20000))['orders'];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['number', 'status', 'customer', 'phone', 'island', 'atoll', 'delivery', 'items', 'total', 'currency', 'payment_method', 'carrier', 'tracking_note', 'placed_at', 'paid_at']);
            foreach ($rows as $r) {
                Csv::put($out, [
                    $r['number'], $r['status'], $r['customer'], $r['address']['phone'] ?? '', $r['address']['island'] ?? '', $r['address']['atoll'] ?? '',
                    $r['delivery']['name'], array_sum(array_column($r['items'], 'quantity')), $r['total'], $r['currency'], $r['payment_method'], $r['carrier'], $r['tracking_note'], $r['placed_at'], $r['paid_at'],
                ]);
            }
            fclose($out);
        }, $scope->vendorSlug.'-orders.csv', ['Content-Type' => 'text/csv']);
    }

    /** B8: one row per order line, for the shop's books — same filters as the orders export. */
    public function exportLines(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(ListVendorOrdersAction::class)->lines($scope, $this->exportFilters($request), (int) config('bookshop.operations.export_max_rows', 20000));

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['number', 'status', 'placed_at', 'paid_at', 'sku', 'title', 'variant', 'quantity', 'unit_price', 'line_total', 'currency', 'island', 'atoll']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['number'], $r['status'], $r['placed_at'], $r['paid_at'], $r['sku'], $r['title'], $r['variant'], $r['quantity'], $r['unit_price'], $r['line_total'], $r['currency'], $r['island'], $r['atoll']]);
            }
            fclose($out);
        }, $scope->vendorSlug.'-order-lines.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{status?: string, from?: string, to?: string}
     */
    private function exportFilters(Request $request): array
    {
        return array_filter($request->validate([
            'status' => 'nullable|string|in:returns,pending_payment,paid,cash_due,needs_attention,processing,ready,dispatched,delivered,cancelled',
            'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d',
        ]), fn ($v) => $v !== null && $v !== '');
    }
}
