<?php

namespace App\Domains\Bookshop\Actions\Money;

use App\Domains\Bookshop\Enums\EarningStatus;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\VendorEarning;
use App\Domains\Commerce\Actions\ResolveRedemptionFundingSourceAction;

/**
 * One earning per PAID order (BOOKSHOP_PLAN §5 "Money", §8; decision 5),
 * idempotent on the order — called from `MarkCheckoutPaidAction`, so a
 * webhook that arrives twice records it once.
 *
 *  - Commission is on **goods only**, at the vendor's rate or the default:
 *    never on the delivery fee, which is the vendor's in full (a boat fee
 *    paid to the carrier never reaches these books).
 *  - A discount **Akuru funded** leaves the vendor's goods at full price
 *    (Akuru's promotion, Akuru's cost); one the **vendor funds** (B7's
 *    vendor-scoped codes) comes off the goods first.
 *  - GST on the commission is added only when Akuru is registered
 *    (config), so the net already matches the monthly commission invoice.
 *  - The earning stays *pending* until the order is delivered and its
 *    return window has passed (`onDelivered` sets the date; the
 *    `bookshop:mature-earnings` command flips it).
 */
class RecordVendorEarningAction
{
    public function execute(Order $order): ?VendorEarning
    {
        if ($order->paid_at === null) {
            return null;
        }
        $existing = VendorEarning::query()->where('order_id', $order->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $vendor = $order->vendor;
        $gross = round((float) $order->subtotal, 2);
        $discount = round((float) $order->discount, 2);
        $funding = $discount > 0 ? $this->funding((int) $order->bookshop_checkout_id) : null;
        $delivery = $order->delivery_carrier_paid ? 0.0 : round((float) $order->delivery_fee, 2);
        $rate = $vendor->effectiveCommissionRate();
        $base = round($funding === 'vendor' ? $gross - $discount : $gross, 2);
        $commission = round($base * $rate / 100, 2);
        $taxRate = config('bookshop.money.issuer_gst_registered') ? (float) config('bookshop.money.commission_tax_rate', 0) : 0.0;
        $tax = round($commission * $taxRate / 100, 2);

        return VendorEarning::query()->create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'gross' => $gross,
            'discount' => $discount,
            'discount_funding' => $funding,
            'delivery_fee' => $delivery,
            'commission_rate' => $rate,
            'commission_base' => $base,
            'commission' => $commission,
            'commission_tax_rate' => $taxRate,
            'commission_tax' => $tax,
            'net' => round($base + $delivery - $commission - $tax, 2),
            'refunded' => 0,
            'paid_amount' => 0,
            'status' => EarningStatus::Pending->value,
            'order_paid_at' => $order->paid_at,
            'available_at' => $order->delivered_at?->copy()->addDays($vendor->returnWindowDays()),
        ]);
    }

    /** Delivered or collected: the return window starts, and the earning matures at its end. */
    public function onDelivered(Order $order): void
    {
        if ($order->delivered_at === null) {
            return;
        }
        VendorEarning::query()->where('order_id', $order->id)->whereNull('available_at')
            ->update(['available_at' => $order->delivered_at->copy()->addDays($order->vendor->returnWindowDays())]);
    }

    /** Akuru-funded unless the code says the vendor pays for it (B7). */
    private function funding(int $checkoutId): string
    {
        $source = app(ResolveRedemptionFundingSourceAction::class)->execute('bookshop_checkout', $checkoutId)['funding_source'] ?? 'akuru';

        return in_array($source, ['vendor', 'writer'], true) ? 'vendor' : 'akuru';
    }
}
