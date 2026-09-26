<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Vendor\VendorQuotesAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B9d: the shop's quote requests (`/vendor/quotes`) —
 * price each line and say how long the price holds, or decline with a
 * note. Thin: which shop and every rule are in the Action, through the
 * `VendorScope`.
 */
class VendorQuotesController extends Controller
{
    use AuthorizesVendor;

    public function index(Request $request): Response
    {
        $scope = $this->authorizeVendor($request);
        $status = $request->validate(['status' => ['nullable', 'string', Rule::in(VendorQuotesAction::STATUSES)]])['status'] ?? null;
        $list = app(VendorQuotesAction::class)->list($scope, $status);

        return Inertia::render('Bookshop/VendorQuotes', [
            't' => trans('shop'),
            'vendor' => ['name' => $scope->vendorName, 'slug' => $scope->vendorSlug, 'role' => $scope->role->value],
            'quotes' => $list['quotes'],
            'counts' => $list['counts'],
            'statuses' => VendorQuotesAction::STATUSES,
            'status' => $status,
            'default_valid_days' => (int) config('bookshop.quotes.default_valid_days', 14),
            'max_valid_days' => (int) config('bookshop.quotes.max_valid_days', 60),
        ]);
    }

    public function quote(Request $request, int $quote): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'prices' => 'required|array|max:500',
            'prices.*' => 'required|numeric|min:0|max:1000000',
            'valid_days' => 'required|integer|min:1|max:365',
            'note' => 'nullable|string|max:2000',
        ]);

        app(VendorQuotesAction::class)->quote($scope, $quote, $data['prices'], (int) $data['valid_days'], $data['note'] ?? null);

        return back()->with('success', __('shop.quote_sent_flash'));
    }

    public function decline(Request $request, int $quote): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate(['note' => 'required|string|max:2000']);

        app(VendorQuotesAction::class)->decline($scope, $quote, $data['note']);

        return back()->with('success', __('shop.quote_declined_flash'));
    }

    /** Every listing gets a CSV (conventions): the quote lines. */
    public function export(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(VendorQuotesAction::class)->list($scope)['quotes'];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['number', 'status', 'organisation', 'customer', 'customer_email', 'contact_phone', 'title', 'variant', 'sku', 'quantity', 'list_price', 'quoted_price', 'valid_until', 'requested_at']);
            foreach ($rows as $row) {
                foreach ($row['items'] as $item) {
                    Csv::put($out, [$row['number'], $row['status'], $row['organisation'], $row['customer'], $row['customer_email'], $row['contact_phone'], $item['title'], $item['variant'], $item['sku'], $item['quantity'], $item['list_price'], $item['quoted_price'], $row['valid_until'], $row['requested_at']]);
                }
            }
            fclose($out);
        }, 'quotes-'.$scope->vendorSlug.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
