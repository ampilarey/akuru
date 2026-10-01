<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\AkuruDeliveryAction;
use App\Domains\Bookshop\Actions\AkuruFulfilmentAction;
use App\Domains\Bookshop\Actions\AkuruStockAction;
use App\Domains\Bookshop\Actions\ResolveVendorScopeAction;
use App\Domains\Bookshop\Actions\Vendor\FulfilVendorOrderAction;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Support\AkuruFulfilment;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * COMMERCE_PARITY_PLAN P6a: Akuru packs and delivers for the shops that
 * choose it. The office's page: orders to pack, stock handed over, charges.
 */
class AdminAkuruController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);

        return Inertia::render('Bookshop/Akuru', [
            't' => trans('shop'),
            'orders' => app(AkuruFulfilmentAction::class)->queue(),
            'shops' => app(AkuruFulfilmentAction::class)->shops(),
            'settings' => AkuruFulfilment::settings(),
            // P6b: Akuru's drivers, and a new one's one-time password, once.
            'drivers' => app(AkuruDeliveryAction::class)->drivers(),
            'driver_added' => $request->session()->get('driver_added'),
        ]);
    }

    public function advance(Request $request, int $order): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['to' => 'required|string|in:processing,ready,dispatched,delivered', 'carrier' => 'nullable|string|max:120', 'tracking_note' => 'nullable|string|max:255', 'cash_received' => 'nullable|boolean']);
        $row = Order::query()->findOrFail($order);
        $scope = app(ResolveVendorScopeAction::class)->forOffice((int) $row->vendor_id, (int) $request->user()->id);
        $moved = app(FulfilVendorOrderAction::class)->advance($scope, $row->id, $data['to'], $data);

        return back()->with('success', __('shop.akuru_order_moved_flash', ['number' => $moved->number, 'status' => __('shop.status_'.$data['to'])]));
    }

    /** P6b: give an order Akuru delivers to a driver. */
    public function assign(Request $request, int $order): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['driver_id' => 'required|integer']);
        app(AkuruDeliveryAction::class)->assign($order, (int) $data['driver_id'], (int) $request->user()->id);

        return back()->with('success', __('shop.driver_assigned_flash'));
    }

    /** P6b: add a driver (an account by email; a new one gets a one-time password) or switch one off and on. */
    public function driver(Request $request, ?int $driver = null): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        if ($driver !== null) {
            app(AkuruDeliveryAction::class)->setActive($driver, (bool) $request->validate(['active' => 'required|boolean'])['active']);

            return back()->with('success', __('shop.driver_saved_flash'));
        }
        $data = $request->validate(['email' => 'required|email|max:255', 'name' => 'required|string|max:120', 'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,20}$/']]);
        $added = app(AkuruDeliveryAction::class)->addDriver($data['email'], $data['name'], $data['phone'] ?? null);

        return back()->with('success', __('shop.driver_saved_flash'))->with('driver_added', ['name' => $added['driver']->name, 'email' => $data['email'], 'temporary_password' => $added['temporary_password']]);
    }

    public function stock(Request $request, int $product): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['direction' => 'required|string|in:in,out', 'quantity' => 'required|integer|min:1|max:100000', 'note' => 'nullable|string|max:120']);
        $stock = app(AkuruStockAction::class);
        $moved = $data['direction'] === 'in'
            ? $stock->receive($product, (int) $data['quantity'], (int) $request->user()->id, $data['note'] ?? null)
            : $stock->giveBack($product, (int) $data['quantity'], (int) $request->user()->id, $data['note'] ?? null);

        return back()->with('success', __('shop.akuru_stock_flash', ['title' => $moved->title, 'at' => (int) $moved->stock_at_akuru]));
    }

    public function settings(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $data = $request->validate(['handling_fee' => 'required|numeric|min:0|max:10000', 'delivery_fee' => 'required|numeric|min:0|max:10000', 'delivery_free_over' => 'nullable|numeric|min:0|max:1000000']);
        app(AkuruFulfilmentAction::class)->saveSettings($data);

        return back()->with('success', __('shop.akuru_settings_flash'));
    }

    /** Every listing gets a CSV (conventions): the orders Akuru packs. */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('bookshop.manage'), 403);
        $rows = app(AkuruFulfilmentAction::class)->queue(5000, all: true);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['order', 'shop', 'status', 'paid', 'delivery', 'recipient', 'address', 'items', 'handling_fee']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['number'], $r['vendor'], $r['status'], $r['paid_at'], $r['delivery'], $r['recipient'], $r['address'], collect($r['items'])->map(fn ($i) => $i['quantity'].' × '.$i['title'])->implode('; '), $r['handling_fee']]);
            }
            fclose($out);
        }, 'bookstore-akuru-orders.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
