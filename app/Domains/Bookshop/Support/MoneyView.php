<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Enums\EarningStatus;
use App\Domains\Bookshop\Enums\PayoutStatus;
use App\Domains\Bookshop\Models\VendorCommissionInvoice;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Bookshop\Models\VendorPayout;
use Illuminate\Support\Collection;

/**
 * How money is shown (BOOKSHOP_PLAN §5 "Money", §7 "Payouts"): the same
 * rows for the shop's page, the office's, the CSVs and the invoice, so
 * they never disagree. Amounts are strings with two decimals.
 */
final class MoneyView
{
    public static function money(float|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * Totals by state. `requestable` is the balance a payout would pay
     * now: matured rows, less what earlier payouts paid, not yet in a
     * request.
     *
     * @param  Collection<int, VendorEarning>  $earnings
     * @param  Collection<int, VendorPayout>  $payouts
     * @return array<string, mixed>
     */
    public static function summary(Collection $earnings, Collection $payouts): array
    {
        $sum = fn (callable $filter) => round((float) $earnings->filter($filter)->sum(fn (VendorEarning $e) => (float) $e->net), 2);
        $requestable = round((float) $earnings
            ->filter(fn (VendorEarning $e) => $e->isMatured() && $e->open_payout_id === null)
            ->sum(fn (VendorEarning $e) => $e->balance()), 2);

        return [
            'awaiting_delivery' => self::money($sum(fn (VendorEarning $e) => $e->status === EarningStatus::Pending && $e->available_at === null)),
            'in_window' => self::money($sum(fn (VendorEarning $e) => $e->status === EarningStatus::Pending && $e->available_at !== null)),
            'available' => self::money($sum(fn (VendorEarning $e) => $e->status === EarningStatus::Available)),
            'paid' => self::money($earnings->sum(fn (VendorEarning $e) => (float) $e->paid_amount)),
            'requested' => self::money($payouts->filter(fn (VendorPayout $p) => $p->status === PayoutStatus::Requested)->sum(fn (VendorPayout $p) => (float) $p->amount)),
            'reversed_count' => $earnings->filter(fn (VendorEarning $e) => $e->status === EarningStatus::Reversed)->count(),
            'requestable' => $requestable,
            'requestable_money' => self::money($requestable),
            'orders_count' => $earnings->count(),
            'lifetime_net' => self::money($earnings->sum(fn (VendorEarning $e) => (float) $e->net)),
            'lifetime_commission' => self::money($earnings->sum(fn (VendorEarning $e) => (float) $e->commission + (float) $e->commission_tax)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function earningRow(VendorEarning $e): array
    {
        return [
            'id' => $e->id,
            'order_number' => $e->order?->number,
            'order_status' => $e->order?->status?->value,
            'paid_at' => $e->order_paid_at?->toDateString(),
            'delivered_at' => $e->order?->delivered_at?->toDateString(),
            'available_at' => $e->available_at?->toDateString(),
            'gross' => self::money($e->gross),
            'discount' => self::money($e->discount),
            'discount_funding' => $e->discount_funding,
            'delivery_fee' => self::money($e->delivery_fee),
            'commission_rate' => self::money($e->commission_rate),
            'commission' => self::money($e->commission),
            'commission_tax' => self::money($e->commission_tax),
            'refunded' => self::money($e->refunded),
            'net' => self::money($e->net),
            'paid_amount' => self::money($e->paid_amount),
            'balance' => self::money($e->balance()),
            'status' => $e->status->value,
            'in_payout' => $e->open_payout_id !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payoutRow(VendorPayout $p, bool $withBank): array
    {
        $bank = (array) ($p->bank_snapshot ?? []);

        return [
            'id' => $p->id,
            'vendor_id' => $p->vendor_id,
            'amount' => self::money($p->amount),
            'currency' => $p->currency,
            'status' => $p->status->value,
            'requested_at' => $p->requested_at?->toDateTimeString(),
            'decided_at' => $p->decided_at?->toDateTimeString(),
            'reference' => $p->reference,
            'note' => $p->note,
            'bank' => $withBank && $bank !== [] ? $bank : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invoiceRow(VendorCommissionInvoice $i): array
    {
        return [
            'id' => $i->id,
            'vendor_id' => $i->vendor_id,
            'number' => $i->number,
            'period' => $i->period_start?->format('F Y'),
            'period_start' => $i->period_start?->toDateString(),
            'period_end' => $i->period_end?->toDateString(),
            'orders_count' => (int) $i->orders_count,
            'sales' => self::money($i->sales),
            'commission' => self::money($i->commission),
            'tax_rate' => self::money($i->tax_rate),
            'tax' => self::money($i->tax),
            'total' => self::money($i->total),
            'currency' => $i->currency,
            'issuer_name' => $i->issuer_name,
            'issuer_tin' => $i->issuer_tin,
            'vendor_legal_name' => $i->vendor_legal_name,
            'vendor_tin' => $i->vendor_tin,
            'issued_at' => $i->issued_at?->toDateString(),
        ];
    }

    /**
     * The orders behind an invoice, as they stand now.
     *
     * @return list<array<string, mixed>>
     */
    public static function invoiceLines(VendorCommissionInvoice $i): array
    {
        return VendorEarning::query()->where('vendor_id', $i->vendor_id)
            ->whereBetween('order_paid_at', [$i->period_start->copy()->startOfDay(), $i->period_end->copy()->endOfDay()])
            ->where('commission', '>', 0)
            ->with('order:id,number')->orderBy('order_paid_at')->get()
            ->map(fn (VendorEarning $e) => [
                'order_number' => $e->order?->number,
                'paid_at' => $e->order_paid_at?->toDateString(),
                'sales' => self::money($e->commissionBaseNow()),
                'rate' => self::money($e->commission_rate),
                'commission' => self::money($e->commission),
                'tax' => self::money($e->commission_tax),
            ])->values()->all();
    }

    /**
     * Statements by month of the order's payment (§5 "statements by month
     * with CSV"): what was sold, what went back, what Akuru kept, what the
     * shop earned, what was paid out that month, and the invoice.
     *
     * @param  Collection<int, VendorEarning>  $earnings
     * @param  Collection<int, VendorPayout>  $payouts
     * @param  Collection<int, VendorCommissionInvoice>  $invoices
     * @return list<array<string, mixed>>
     */
    public static function statements(Collection $earnings, Collection $payouts, Collection $invoices): array
    {
        $months = $earnings->groupBy(fn (VendorEarning $e) => $e->order_paid_at->format('Y-m'));
        $paidByMonth = $payouts->filter(fn (VendorPayout $p) => $p->status === PayoutStatus::Paid)->groupBy(fn (VendorPayout $p) => $p->decided_at->format('Y-m'));
        $invoiceByMonth = $invoices->keyBy(fn (VendorCommissionInvoice $i) => $i->period_start->format('Y-m'));
        $keys = $months->keys()->merge($paidByMonth->keys())->unique()->sortDesc()->values();

        return $keys->map(function (string $key) use ($months, $paidByMonth, $invoiceByMonth) {
            $rows = $months->get($key, collect());
            $invoice = $invoiceByMonth->get($key);

            return [
                'month' => $key,
                'label' => \Carbon\Carbon::createFromFormat('Y-m', $key)->format('F Y'),
                'orders' => $rows->count(),
                'gross' => self::money($rows->sum(fn (VendorEarning $e) => (float) $e->gross)),
                'discounts_vendor' => self::money($rows->filter(fn (VendorEarning $e) => $e->discount_funding === 'vendor')->sum(fn (VendorEarning $e) => (float) $e->discount)),
                'delivery' => self::money($rows->sum(fn (VendorEarning $e) => (float) $e->delivery_fee * (1 - $e->refundedFraction()))),
                'refunded' => self::money($rows->sum(fn (VendorEarning $e) => (float) $e->refunded)),
                'commission' => self::money($rows->sum(fn (VendorEarning $e) => (float) $e->commission)),
                'commission_tax' => self::money($rows->sum(fn (VendorEarning $e) => (float) $e->commission_tax)),
                'net' => self::money($rows->sum(fn (VendorEarning $e) => (float) $e->net)),
                'paid_out' => self::money($paidByMonth->get($key, collect())->sum(fn (VendorPayout $p) => (float) $p->amount)),
                'invoice_number' => $invoice?->number,
                'invoice_total' => $invoice !== null ? self::money($invoice->total) : null,
            ];
        })->values()->all();
    }
}
