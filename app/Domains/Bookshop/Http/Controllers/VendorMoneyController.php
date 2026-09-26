<?php

namespace App\Domains\Bookshop\Http\Controllers;

use App\Domains\Bookshop\Actions\Vendor\ListVendorMoneyAction;
use App\Domains\Bookshop\Actions\Vendor\RequestVendorPayoutAction;
use App\Domains\Bookshop\Actions\Vendor\SaveVendorBankDetailsAction;
use App\Domains\Bookshop\Http\Controllers\Concerns\AuthorizesVendor;
use App\Http\Controllers\Controller;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * BOOKSHOP_PLAN slice B6: the shop's money (`/vendor/money`) — earnings by
 * order, the balance it may ask for, bank details, payout requests,
 * Akuru's commission invoices, statements by month; CSVs of the earnings
 * and the statements. Thin: every figure is the Actions'.
 */
class VendorMoneyController extends Controller
{
    use AuthorizesVendor;

    public function index(Request $request): Response
    {
        $scope = $this->authorizeVendor($request);

        return Inertia::render('Bookshop/VendorMoney', [
            't' => trans('shop'),
            'vendor' => ['name' => $scope->vendorName, 'slug' => $scope->vendorSlug, 'role' => $scope->role->value],
            'money' => app(ListVendorMoneyAction::class)->execute($scope),
        ]);
    }

    public function saveBankDetails(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);
        $data = $request->validate([
            'bank_name' => 'required|string|max:120',
            'account_name' => 'required|string|max:160',
            'account_number' => 'required|string|max:50',
        ]);

        app(SaveVendorBankDetailsAction::class)->save($scope, $data);

        return back()->with('success', __('shop.bank_details_saved_flash'));
    }

    public function requestPayout(Request $request): RedirectResponse
    {
        $scope = $this->authorizeVendor($request);

        $payout = app(RequestVendorPayoutAction::class)->execute($scope);

        return back()->with('success', __('shop.payout_requested_flash', ['amount' => $payout->currency.' '.number_format((float) $payout->amount, 2)]));
    }

    /** A commission invoice from Akuru, on one page for the printer. */
    public function invoice(Request $request, int $invoice): Response
    {
        $scope = $this->authorizeVendor($request);

        return Inertia::render('Bookshop/CommissionInvoice', [
            't' => trans('shop'),
            'invoice' => app(ListVendorMoneyAction::class)->invoice($scope, $invoice),
            'back_url' => route('vendor.money.index'),
        ]);
    }

    /** Every listing gets a CSV (conventions): the earnings by order. */
    public function exportEarnings(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(ListVendorMoneyAction::class)->execute($scope)['earnings'];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['order', 'paid_at', 'delivered_at', 'available_at', 'goods', 'discount', 'discount_funding', 'delivery_fee', 'commission_rate', 'commission', 'commission_tax', 'refunded', 'net', 'paid_amount', 'balance', 'status']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['order_number'], $r['paid_at'], $r['delivered_at'], $r['available_at'], $r['gross'], $r['discount'], $r['discount_funding'], $r['delivery_fee'], $r['commission_rate'], $r['commission'], $r['commission_tax'], $r['refunded'], $r['net'], $r['paid_amount'], $r['balance'], $r['status']]);
            }
            fclose($out);
        }, $scope->vendorSlug.'-earnings.csv', ['Content-Type' => 'text/csv']);
    }

    /** Every listing gets a CSV (conventions): the statements by month. */
    public function exportStatements(Request $request): StreamedResponse
    {
        $scope = $this->authorizeVendor($request);
        $rows = app(ListVendorMoneyAction::class)->execute($scope)['statements'];

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            Csv::put($out, ['month', 'orders', 'goods', 'vendor_funded_discounts', 'delivery', 'refunded', 'commission', 'commission_tax', 'net', 'paid_out', 'invoice', 'invoice_total']);
            foreach ($rows as $r) {
                Csv::put($out, [$r['month'], $r['orders'], $r['gross'], $r['discounts_vendor'], $r['delivery'], $r['refunded'], $r['commission'], $r['commission_tax'], $r['net'], $r['paid_out'], $r['invoice_number'], $r['invoice_total']]);
            }
            fclose($out);
        }, $scope->vendorSlug.'-statements.csv', ['Content-Type' => 'text/csv']);
    }
}
