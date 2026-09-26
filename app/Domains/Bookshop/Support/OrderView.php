<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Enums\OrderStatus;
use App\Domains\Bookshop\Enums\ReturnStatus;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use App\Domains\Bookshop\Models\OrderRefund;
use App\Domains\Bookshop\Models\OrderReturn;
use Illuminate\Support\Carbon;

/**
 * The parts of an order both sides read the same way (B3): what can happen
 * next, how much of each line can still go back, what a returned unit is
 * worth, and — decision 15 — whether the shop may still see the customer's
 * phone and address.
 */
final class OrderView
{
    /** The steps a shop may take from here, in order. */
    public static function nextSteps(Order $order): array
    {
        $collection = $order->isCollection();

        return match ($order->status) {
            OrderStatus::Paid, OrderStatus::CashDue, OrderStatus::NeedsAttention => ['processing', $collection ? 'ready' : 'dispatched'],
            OrderStatus::Processing => [$collection ? 'ready' : 'dispatched'],
            OrderStatus::Ready, OrderStatus::Dispatched => ['delivered'],
            default => [],
        };
    }

    /** Delivered or collected, cancelled: the order is over. */
    public static function closedAt(Order $order): ?Carbon
    {
        return $order->delivered_at ?? $order->cancelled_at;
    }

    /** The last day a return may be asked for, or null before delivery. */
    public static function returnsUntil(Order $order): ?Carbon
    {
        if ($order->status !== OrderStatus::Delivered || $order->delivered_at === null) {
            return null;
        }

        return $order->delivered_at->copy()->addDays($order->vendor->returnWindowDays())->endOfDay();
    }

    public static function returnsOpen(Order $order): bool
    {
        $until = self::returnsUntil($order);

        return $until !== null && now()->lte($until);
    }

    /** Decision 15: the shop sees the customer's contact until the order closed and the return window passed. */
    public static function contactVisible(Order $order): bool
    {
        $closed = self::closedAt($order);

        return $closed === null || now()->lte($closed->copy()->addDays($order->vendor->returnWindowDays())->endOfDay());
    }

    /**
     * @param  array<string, mixed>|null  $address
     * @return array<string, mixed>
     */
    public static function maskAddress(?array $address): array
    {
        $address ??= [];
        $phone = (string) ($address['phone'] ?? '');

        return [
            'recipient_name' => $address['recipient_name'] ?? null,
            'phone' => $phone === '' ? '' : substr($phone, 0, 2).str_repeat('•', max(0, strlen($phone) - 4)).substr($phone, -2),
            'atoll' => $address['atoll'] ?? null,
            'island' => $address['island'] ?? null,
            'street' => '•••',
            'notes' => null,
            'masked' => true,
        ];
    }

    /** Units of a line not already returned or waiting to be. */
    public static function returnable(OrderItem $item, iterable $returns): int
    {
        $taken = 0;
        foreach ($returns as $return) {
            /** @var OrderReturn $return */
            if ((int) $return->order_item_id === (int) $item->id && $return->status !== ReturnStatus::Declined) {
                $taken += (int) $return->quantity;
            }
        }

        return max(0, (int) $item->quantity - $taken);
    }

    /**
     * What `quantity` units of a line are worth back: its share of the
     * order's goods after the discount, which B2 spread over the lines in
     * proportion to their totals.
     */
    public static function returnValue(Order $order, OrderItem $item, int $quantity): float
    {
        $subtotal = (float) $order->subtotal;
        $line = (float) $item->line_total;
        $share = $subtotal > 0 ? (float) $order->discount * $line / $subtotal : 0.0;
        $perUnit = $item->quantity > 0 ? ($line - $share) / (int) $item->quantity : 0.0;

        return round(max(0, $perUnit * $quantity), 2);
    }

    /** Has the delivery fee already gone back on this order? */
    public static function deliveryRefunded(Order $order): bool
    {
        return $order->returns->contains(fn (OrderReturn $r) => $r->refunds_delivery && $r->status === ReturnStatus::Accepted);
    }

    /**
     * @return array<string, mixed>
     */
    public static function refund(OrderRefund $refund): array
    {
        return [
            'id' => $refund->id,
            'amount' => (string) $refund->amount,
            'paid_with' => $refund->paid_with,
            'status' => $refund->status->value,
            'destination' => $refund->destination,
            'reason' => $refund->reason,
            'processed_at' => $refund->processed_at?->toDateTimeString(),
            'created_at' => $refund->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function returnRow(OrderReturn $return): array
    {
        return [
            'id' => $return->id,
            'item_id' => (int) $return->order_item_id,
            'title' => $return->item?->title,
            'variant' => $return->item?->variant_name,
            'quantity' => (int) $return->quantity,
            'reason' => $return->reason->value,
            'note' => $return->note,
            'status' => $return->status->value,
            'refund_amount' => (string) $return->refund_amount,
            'refunds_delivery' => (bool) $return->refunds_delivery,
            'restocked' => (bool) $return->restocked,
            'decision_note' => $return->decision_note,
            'requested_at' => $return->created_at?->toDateTimeString(),
            'decided_at' => $return->decided_at?->toDateTimeString(),
        ];
    }
}
