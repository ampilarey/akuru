<?php

namespace App\Domains\Bookshop\Actions\Money;

use App\Domains\Bookshop\Enums\EarningStatus;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\VendorEarning;

/**
 * Money went back to the customer, so the vendor's earning goes back too
 * (BOOKSHOP_PLAN §4 "Refunds": "the vendor's earning reverses in every
 * case"). In proportion: a refund of a third of the order reverses a
 * third of the commission, its tax and the net. Everything is recomputed
 * from what has gone back so far, so a cancellation that also raises a
 * refund reverses once. A sale that went back in full is *reversed*; one
 * already paid out carries a negative balance the next payout claws back.
 */
class ReverseVendorEarningAction
{
    public function execute(Order $order, float $customerAmount): ?VendorEarning
    {
        $earning = VendorEarning::query()->where('order_id', $order->id)->lockForUpdate()->first();
        if ($earning === null) {
            return null;
        }
        $total = $earning->orderTotal();
        if ($total > 0) {
            $earning->refunded = min($total, round((float) $earning->refunded + max(0, $customerAmount), 2));
        }
        // A free order (a discount brought it to zero) can only go back whole.
        $fraction = $total > 0 ? $earning->refundedFraction() : 1.0;

        return $this->apply($earning, $fraction);
    }

    /** The whole sale undone, whatever was paid for it (a cancellation). */
    public function cancel(Order $order): ?VendorEarning
    {
        $earning = VendorEarning::query()->where('order_id', $order->id)->lockForUpdate()->first();
        if ($earning === null) {
            return null;
        }
        $earning->refunded = $earning->orderTotal();

        return $this->apply($earning, 1.0);
    }

    private function apply(VendorEarning $earning, float $fraction): VendorEarning
    {
        $keep = max(0.0, 1 - $fraction);
        $commission = round((float) $earning->commission_base * (float) $earning->commission_rate / 100 * $keep, 2);
        $tax = round($commission * (float) $earning->commission_tax_rate / 100, 2);
        $delivery = round((float) $earning->delivery_fee * $keep, 2);
        $earning->commission = $commission;
        $earning->commission_tax = $tax;
        $earning->net = $fraction >= 1 ? 0 : round((float) $earning->commission_base * $keep + $delivery - $commission - $tax, 2);
        if ($fraction >= 1) {
            $earning->status = EarningStatus::Reversed;
        }
        $earning->save();

        return $earning;
    }
}
