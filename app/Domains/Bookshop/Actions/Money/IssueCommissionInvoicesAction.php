<?php

namespace App\Domains\Bookshop\Actions\Money;

use App\Domains\Bookshop\Actions\NotifyBookshopUserAction;
use App\Domains\Bookshop\Models\Vendor;
use App\Domains\Bookshop\Models\VendorCommissionInvoice;
use App\Domains\Bookshop\Models\VendorEarning;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Akuru's monthly commission tax invoice to each vendor (BOOKSHOP_PLAN §8,
 * audit finding 4): the commission on the orders paid in the month, after
 * whatever went back by the day of issue, with GST on it only when Akuru
 * is registered; under Akuru's name and TIN from config. One per vendor
 * and month, numbered `ACI-YYYYMM-<vendor code>`; a month with no
 * commission gets none. Run on the first of the month by
 * `bookshop:issue-commission-invoices`, or by the office for any month.
 *
 * @return list<VendorCommissionInvoice> the invoices issued (not those already there)
 */
class IssueCommissionInvoicesAction
{
    public function execute(CarbonInterface $month, ?int $issuedBy = null): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $issued = [];

        $vendorIds = VendorEarning::query()->whereBetween('order_paid_at', [$start, $end])->distinct()->pluck('vendor_id');
        foreach (Vendor::query()->whereIn('id', $vendorIds)->get() as $vendor) {
            $invoice = DB::transaction(function () use ($vendor, $start, $end, $issuedBy) {
                if (VendorCommissionInvoice::query()->where('vendor_id', $vendor->id)->where('period_start', $start->toDateString())->exists()) {
                    return null;
                }
                $earnings = VendorEarning::query()->where('vendor_id', $vendor->id)->whereBetween('order_paid_at', [$start, $end])->lockForUpdate()->get();
                $commission = round((float) $earnings->sum('commission'), 2);
                if ($commission <= 0) {
                    return null;
                }
                $tax = round((float) $earnings->sum('commission_tax'), 2);
                $taxRate = (float) ($earnings->first(fn (VendorEarning $e) => (float) $e->commission_tax_rate > 0)?->commission_tax_rate ?? 0);

                return VendorCommissionInvoice::query()->create([
                    'vendor_id' => $vendor->id,
                    'number' => self::number($start, (string) $vendor->code),
                    'period_start' => $start->toDateString(),
                    'period_end' => $end->toDateString(),
                    'orders_count' => $earnings->filter(fn (VendorEarning $e) => (float) $e->commission > 0)->count(),
                    'sales' => round($earnings->sum(fn (VendorEarning $e) => $e->commissionBaseNow()), 2),
                    'commission' => $commission,
                    'tax_rate' => $taxRate,
                    'tax' => $tax,
                    'total' => round($commission + $tax, 2),
                    'currency' => config('bookshop.currency', 'MVR'),
                    'issuer_name' => (string) config('bookshop.money.issuer_name', 'Akuru Institute'),
                    'issuer_tin' => config('bookshop.money.issuer_tin'),
                    'vendor_legal_name' => $vendor->legal_name ?: $vendor->name,
                    'vendor_tin' => $vendor->tin,
                    'issued_at' => now(),
                    'issued_by' => $issuedBy,
                ]);
            });
            if ($invoice !== null) {
                $issued[] = $invoice;
                app(NotifyBookshopUserAction::class)->vendor($vendor->id, __('shop.notice_invoice_title'), __('shop.notice_invoice_body', ['number' => $invoice->number, 'amount' => $invoice->currency.' '.number_format((float) $invoice->total, 2)]), '/vendor/money', 'invoice');
            }
        }

        return $issued;
    }

    public static function number(CarbonInterface $periodStart, string $vendorCode): string
    {
        return config('bookshop.money.invoice_prefix', 'ACI').'-'.$periodStart->format('Ym').'-'.strtoupper($vendorCode);
    }
}
