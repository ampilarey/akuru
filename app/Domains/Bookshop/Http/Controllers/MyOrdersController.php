<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Cart\BuyAgainAction;
use App\Domains\Bookshop\Actions\Money\ReferralCreditAction;
use App\Domains\Bookshop\Actions\OrderComplaintAction;
use App\Domains\Bookshop\Actions\Orders\CustomerOrderAction;
use App\Domains\Bookshop\Actions\Orders\ListMyOrdersAction;
use App\Domains\Bookshop\Actions\Orders\PresentOrderAction;
use App\Domains\Bookshop\Actions\Orders\RequestReturnAction;
use App\Domains\Bookshop\Actions\Orders\TrackOrderAction;
use App\Domains\Bookshop\Enums\ReturnReason;
use App\Domains\Bookshop\Http\Controllers\Concerns\ResolvesCart;
use App\Domains\Bookshop\Models\OrderComplaint;
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
    use ResolvesCart;

    public function index(Request $request)
    {
        abort_unless($request->user() !== null, 403);

        return view('public.shop.orders.index', [
            'orders' => app(ListMyOrdersAction::class)->execute((int) $request->user()->id),
            // STATUS §5ln: their share link while referral credit is on.
            'invite' => app(ReferralCreditAction::class)->invite((int) $request->user()->id),
        ]);
    }

    /** §5lj: track an order by its number and phone, signed in or not. */
    public function track(Request $request)
    {
        $data = $request->validate(['number' => 'nullable|string|max:40', 'phone' => 'nullable|string|max:30']);
        $order = ($data['number'] ?? null) && ($data['phone'] ?? null)
            ? app(TrackOrderAction::class)->execute($data['number'], $data['phone'])
            : null;

        return view('public.shop.orders.show', ['tracking' => true, 'order' => $order, 'query' => $data]);
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

    /** §5ld: this order's items back into the cart, at today's prices. */
    public function buyAgain(Request $request, string $number): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $actions = app(BuyAgainAction::class);
        $order = $actions->order((int) $request->user()->id, $number);
        abort_if($order === null, 404);

        $result = $actions->execute($this->cart($request, create: true), $order);
        $flash = redirect()->route('public.shop.cart')->with('success', __('shop.buy_again_added_flash', ['count' => $result['added']]));

        return $result['skipped'] === [] ? $flash : $flash->with('warning', __('shop.buy_again_skipped_flash', ['titles' => implode(', ', $result['skipped'])]));
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

    /** COMMERCE_PARITY_PLAN P7a: report a problem with this order, with a photo if there is one. */
    public function complain(Request $request, string $number): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'kind' => 'required|string|in:'.implode(',', OrderComplaint::KINDS),
            'body' => 'required|string|max:2000',
            'photo' => ['nullable', 'file', 'max:10240', 'mimetypes:'.implode(',', OrderComplaintAction::PHOTO_MIMES)],
        ]);
        app(OrderComplaintAction::class)->report((int) $request->user()->id, $number, $data['kind'], $data['body'], $request->file('photo'));

        return back()->with('success', __('shop.complaint_sent_flash'));
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
