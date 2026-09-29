<?php

namespace App\Domains\Bookshop\Actions\Orders;

use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;

/**
 * Track an order without signing in (STATUS §5lj): the order's number and
 * the phone number it is going to — both, or nothing. What it shows is what
 * a courier's tracking page would: the shop, the status, the steps and the
 * tracking note, and what is in it. Never the address, the name, the money
 * or the messages; those stay behind the customer's sign-in.
 */
class TrackOrderAction
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $number, string $phone): ?array
    {
        $digits = self::digits($phone);
        if (strlen($digits) < 7) {
            return null;
        }
        $order = Order::query()->where('number', trim($number))->with(['vendor', 'items'])->first();
        if ($order === null || self::digits((string) ($order->address_snapshot['phone'] ?? '')) !== $digits) {
            return null;
        }

        return [
            'number' => $order->number,
            'status' => $order->status->value,
            'placed_at' => $order->created_at?->toDateString(),
            'vendor' => $order->vendor?->name,
            'collection' => $order->isCollection(),
            'carrier' => $order->carrier,
            'tracking_note' => $order->tracking_note,
            'steps' => [
                'paid' => $order->paid_at?->toDateTimeString(),
                'processing' => $order->processing_at?->toDateTimeString(),
                'ready' => $order->ready_at?->toDateTimeString(),
                'dispatched' => $order->dispatched_at?->toDateTimeString(),
                'delivered' => $order->delivered_at?->toDateTimeString(),
            ],
            'items' => $order->items->map(fn (OrderItem $i) => ['title' => $i->title, 'variant' => $i->variant_name, 'quantity' => (int) $i->quantity])->values()->all(),
        ];
    }

    /** The last seven digits: "+960 771-2345" and "7712345" are the same phone. */
    private static function digits(string $phone): string
    {
        return substr(preg_replace('/\D+/', '', $phone) ?? '', -7);
    }
}
