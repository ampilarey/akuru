<?php

namespace App\Domains\Bookshop\Actions\Vendor;

use App\Domains\Bookshop\Actions\Money\MatureVendorEarningsAction;
use App\Domains\Bookshop\DTOs\VendorScope;
use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Enums\PayoutStatus;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCommissionInvoice;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Bookshop\Models\VendorPayout;
use App\Domains\Bookshop\Support\MoneyView;

/**
 * The shop's money page (BOOKSHOP_PLAN §5 "Money"): what it has earned by
 * state — waiting for delivery, in the return window, available, paid,
 * reversed — the balance it may ask for, every earning by order, its
 * payouts, Akuru's commission invoices, and statements by month (sales,
 * refunds, commission and its tax, net, paid out), each with a CSV.
 * Only the scope's vendor's rows, ever; matures due earnings first.
 */
class ListVendorMoneyAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(VendorScope $scope): array
    {
        app(MatureVendorEarningsAction::class)->execute($scope->vendorId);
        $vendor = Vendor::query()->findOrFail($scope->vendorId);
        $earnings = VendorEarning::query()->where('vendor_id', $scope->vendorId)->with('order:id,number,status,delivered_at')->orderByDesc('order_paid_at')->get();
        $payouts = VendorPayout::query()->where('vendor_id', $scope->vendorId)->orderByDesc('requested_at')->limit(100)->get();
        $invoices = VendorCommissionInvoice::query()->where('vendor_id', $scope->vendorId)->orderByDesc('period_start')->get();
        $bank = app(SaveVendorBankDetailsAction::class)->get($scope);
        $summary = MoneyView::summary($earnings, $payouts);
        $minimum = (float) config('bookshop.money.min_payout', 100);
        $enabled = (bool) config('bookshop.money.payouts_enabled');

        return [
            'summary' => $summary + [
                // B11 (§5 "returns rate"): of the orders delivered, how many had money go back.
                'delivered_orders' => $delivered = $earnings->filter(fn (VendorEarning $e) => $e->order?->status === OrderStatus::Delivered)->count(),
                'returned_orders' => $returned = $earnings->filter(fn (VendorEarning $e) => $e->order?->status === OrderStatus::Delivered && (float) $e->refunded > 0)->count(),
                'returns_rate' => $delivered > 0 ? number_format($returned * 100 / $delivered, 1) : null,
                'commission_rate' => number_format($vendor->effectiveCommissionRate(), 2, '.', ''),
                'return_window_days' => $vendor->returnWindowDays(),
                'min_payout' => number_format($minimum, 2, '.', ''),
                'payouts_enabled' => $enabled,
                'has_bank_details' => $bank !== null,
                'has_open_payout' => $payouts->contains(fn (VendorPayout $p) => $p->status === PayoutStatus::Requested),
                'can_request' => $enabled && $bank !== null && $summary['requestable'] >= $minimum && ! $payouts->contains(fn (VendorPayout $p) => $p->status === PayoutStatus::Requested),
            ],
            'bank' => $bank,
            'earnings' => $earnings->map(fn (VendorEarning $e) => MoneyView::earningRow($e))->values()->all(),
            'payouts' => $payouts->map(fn (VendorPayout $p) => MoneyView::payoutRow($p, withBank: false))->values()->all(),
            'invoices' => $invoices->map(fn (VendorCommissionInvoice $i) => MoneyView::invoiceRow($i))->values()->all(),
            'statements' => MoneyView::statements($earnings, $payouts, $invoices),
            'currency' => config('bookshop.currency', 'MVR'),
        ];
    }

    /**
     * One of the shop's commission invoices, for the printable page.
     *
     * @return array<string, mixed>
     */
    public function invoice(VendorScope $scope, int $invoiceId): array
    {
        $invoice = VendorCommissionInvoice::query()->where('vendor_id', $scope->vendorId)->whereKey($invoiceId)->firstOrFail();

        return MoneyView::invoiceRow($invoice) + ['lines' => MoneyView::invoiceLines($invoice)];
    }
}
