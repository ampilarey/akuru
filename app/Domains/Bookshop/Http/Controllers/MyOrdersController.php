<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Orders\CustomerOrderAction;
use App\Domains\Bookshop\Actions\Orders\ListMyOrdersAction;
use App\Domains\Bookshop\Actions\Orders\PresentOrderAction;
use App\Domains\Bookshop\Actions\Orders\RequestReturnAction;
use App\Domains\Bookshop\Enums\ReturnReason;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B2: a customer's own orders and their receipts.
 * Own only — the actions take the user id, never the request's say-so.
 */
class MyOrdersController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user() !== null, 403);

        return view('public.shop.orders.index', [
            'orders' => app(ListMyOrdersAction::class)->execute((int) $request->user()->id),
        ]);
    }

    public function show(Request $request, string $number)
    {
        abort_unless($request->user() !== null, 403);
        $order = app(PresentOrderAction::class)->execute((int) $request->user()->id, $number);
        abort_if($order === null, 404);

        return view('public.shop.orders.show', [
            'order' => $order,
            'reasons' => array_map(fn (ReturnReason $r) => $r->value, ReturnReason::cases()),
        ]);
    }

    /** B3: cancel before it leaves the shop; the money goes back. */
    public function cancel(Request $request, string $number): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate(['reason' => 'required|string|max:500']);

        app(CustomerOrderAction::class)->cancel((int) $request->user()->id, $number, $data['reason']);

        return back()->with('success', __('shop.order_cancelled_flash'));
    }

    /** B3: ask to send part of a delivered order back, inside the window. */
    public function requestReturn(Request $request, string $number): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'item_id' => 'required|integer',
            'quantity' => 'required|integer|min:1|max:1000',
            'reason' => 'required|string|max:30',
            'note' => 'nullable|string|max:1000',
        ]);

        app(RequestReturnAction::class)->execute((int) $request->user()->id, $number, (int) $data['item_id'], (int) $data['quantity'], $data['reason'], $data['note'] ?? null);

        return back()->with('success', __('shop.return_requested_flash'));
    }

    /** B3: write to the shop about this order, on the Messages threads. */
    public function message(Request $request, string $number): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate(['body' => 'required|string|max:5000']);

        app(CustomerOrderAction::class)->message((int) $request->user()->id, $number, $data['body']);

        return back()->with('success', __('shop.message_sent_flash'));
    }

    /** Every listing gets a CSV (conventions): my orders. */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);
        $rows = app(ListMyOrdersAction::class)->execute((int) $request->user()->id, 1000);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['number', 'status', 'shop', 'items', 'delivery', 'total', 'currency', 'placed_at', 'paid_at']);
            foreach ($rows as $row) {
                Csv::put($out, [$row['number'], $row['status'], $row['vendor']['name'], $row['item_count'], $row['delivery']['name'], $row['total'], $row['currency'], $row['placed_at'], $row['paid_at']]);
            }
            fclose($out);
        }, 'my-orders.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
