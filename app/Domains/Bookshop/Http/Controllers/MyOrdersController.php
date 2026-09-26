<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Orders\ListMyOrdersAction;
use App\Domains\Bookshop\Actions\Orders\PresentOrderAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
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

        return view('public.shop.orders.show', ['order' => $order]);
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
