<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Shop\CustomerQuotesAction;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B9d: a school's (or any customer's) bulk quotes —
 * ask a shop to price its lines in the cart, see the price, accept it into
 * the cart or withdraw. Own only — the action takes the user id.
 */
class MyQuotesController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user() !== null, 403);

        return view('public.shop.quotes.index', ['quotes' => app(CustomerQuotesAction::class)->list((int) $request->user()->id)]);
    }

    public function show(Request $request, string $number)
    {
        abort_unless($request->user() !== null, 403);
        $quote = app(CustomerQuotesAction::class)->show((int) $request->user()->id, $number);
        abort_if($quote === null, 404);

        return view('public.shop.quotes.show', ['quote' => $quote]);
    }

    /** From the cart: this shop's lines, for a school or group. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        $data = $request->validate([
            'vendor' => 'required|string|max:80',
            'organisation' => 'required|string|max:160',
            'contact_phone' => 'nullable|string|max:30',
            'note' => 'nullable|string|max:2000',
        ]);

        $quote = app(CustomerQuotesAction::class)->request((int) $request->user()->id, $data['vendor'], $data);

        return redirect()->route('public.shop.quotes.show', $quote->number)->with('success', __('shop.quote_requested_flash'));
    }

    public function accept(Request $request, string $number): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        app(CustomerQuotesAction::class)->accept((int) $request->user()->id, $number);

        return redirect()->route('public.shop.cart')->with('success', __('shop.quote_accepted_flash'));
    }

    public function withdraw(Request $request, string $number): RedirectResponse
    {
        abort_unless($request->user() !== null, 403);
        app(CustomerQuotesAction::class)->withdraw((int) $request->user()->id, $number);

        return back()->with('success', __('shop.quote_withdrawn_flash'));
    }

    /** Every listing gets a CSV (conventions): my quotes. */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user() !== null, 403);
        $rows = app(CustomerQuotesAction::class)->list((int) $request->user()->id);

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['number', 'status', 'shop', 'organisation', 'items', 'list_total', 'quoted_total', 'currency', 'valid_until', 'requested_at']);
            foreach ($rows as $row) {
                Csv::put($out, [$row['number'], $row['status'], $row['vendor']['name'], $row['organisation'], array_sum(array_column($row['items'], 'quantity')), $row['list_total'], $row['quoted_total'], $row['currency'], $row['valid_until'], $row['requested_at']]);
            }
            fclose($out);
        }, 'my-quotes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
