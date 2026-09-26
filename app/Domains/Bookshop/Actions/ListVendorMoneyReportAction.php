<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Actions\Money\MatureVendorEarningsAction;
use App\Domains\Bookshop\Enums\PayoutStatus;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCommissionInvoice;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Bookshop\Models\VendorPayout;
use App\Domains\Bookshop\Support\MoneyView;

/**
 * The office's money view (BOOKSHOP_PLAN §7 "Payouts" and "Reports"):
 * payout requests to decide — with the bank details they go to — the
 * payout history, every vendor's balances, the commission invoices
 * issued, and the tax report by month (commission, GST on it, sales it
 * was charged on). Each listing has a CSV.
 */
class ListVendorMoneyReportAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(): array
    {
        app(MatureVendorEarningsAction::class)->execute();
        $vendors = Vendor::query()->orderBy('name')->get()->keyBy('id');
        $earnings = VendorEarning::query()->get();
        $payouts = VendorPayout::query()->orderByDesc('requested_at')->limit(200)->get();
        $invoices = VendorCommissionInvoice::query()->orderByDesc('period_start')->orderBy('vendor_id')->limit(200)->get();

        return [
            'requests' => $payouts->filter(fn (VendorPayout $p) => $p->status === PayoutStatus::Requested)->sortBy('requested_at')
                ->map(fn (VendorPayout $p) => MoneyView::payoutRow($p, withBank: true) + ['vendor' => $vendors->get($p->vendor_id)?->name])->values()->all(),
            'payouts' => $payouts->filter(fn (VendorPayout $p) => $p->status !== PayoutStatus::Requested)
                ->map(fn (VendorPayout $p) => MoneyView::payoutRow($p, withBank: false) + ['vendor' => $vendors->get($p->vendor_id)?->name])->values()->all(),
            'vendors' => $vendors->map(function (Vendor $v) use ($earnings, $payouts) {
                $own = $earnings->where('vendor_id', $v->id);

                return ['id' => $v->id, 'name' => $v->name, 'slug' => $v->slug, 'commission_rate' => MoneyView::money($v->effectiveCommissionRate())]
                    + MoneyView::summary($own, $payouts->where('vendor_id', $v->id));
            })->filter(fn (array $row) => $row['orders_count'] > 0)->values()->all(),
            'invoices' => $invoices->map(fn (VendorCommissionInvoice $i) => MoneyView::invoiceRow($i) + ['vendor' => $vendors->get($i->vendor_id)?->name])->values()->all(),
            'tax_report' => $this->taxReport($earnings, $invoices),
            'issuer' => ['name' => config('bookshop.money.issuer_name'), 'tin' => config('bookshop.money.issuer_tin'), 'gst_registered' => (bool) config('bookshop.money.issuer_gst_registered'), 'tax_rate' => MoneyView::money(config('bookshop.money.commission_tax_rate', 0))],
            'last_month' => now()->subMonthNoOverflow()->format('Y-m'),
            'currency' => config('bookshop.currency', 'MVR'),
        ];
    }

    /**
     * One commission invoice, for the office's printable page.
     *
     * @return array<string, mixed>
     */
    public function invoice(int $invoiceId): array
    {
        $invoice = VendorCommissionInvoice::query()->whereKey($invoiceId)->firstOrFail();

        return MoneyView::invoiceRow($invoice) + ['lines' => MoneyView::invoiceLines($invoice)];
    }

    /**
     * By month of the order's payment: sales charged, commission, GST on it,
     * refunds to customers, and how much of the commission is invoiced.
     *
     * @return list<array<string, string|int>>
     */
    private function taxReport($earnings, $invoices): array
    {
        $invoiced = $invoices->groupBy(fn (VendorCommissionInvoice $i) => $i->period_start->format('Y-m'));

        return $earnings->groupBy(fn (VendorEarning $e) => $e->order_paid_at->format('Y-m'))->sortKeysDesc()
            ->map(fn ($rows, string $key) => [
                'month' => $key,
                'label' => \Carbon\Carbon::createFromFormat('Y-m', $key)->format('F Y'),
                'orders' => $rows->count(),
                'sales' => MoneyView::money($rows->sum(fn (VendorEarning $e) => $e->commissionBaseNow())),
                'gross_paid' => MoneyView::money($rows->sum(fn (VendorEarning $e) => $e->orderTotal())),
                'refunded' => MoneyView::money($rows->sum(fn (VendorEarning $e) => (float) $e->refunded)),
                'commission' => MoneyView::money($rows->sum(fn (VendorEarning $e) => (float) $e->commission)),
                'commission_tax' => MoneyView::money($rows->sum(fn (VendorEarning $e) => (float) $e->commission_tax)),
                'vendor_net' => MoneyView::money($rows->sum(fn (VendorEarning $e) => (float) $e->net)),
                'invoices' => $invoiced->get($key, collect())->count(),
                'invoiced' => MoneyView::money($invoiced->get($key, collect())->sum(fn (VendorCommissionInvoice $i) => (float) $i->total)),
            ])->values()->all();
    }
}
