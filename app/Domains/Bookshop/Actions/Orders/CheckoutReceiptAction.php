<?php

namespace App\Domains\Bookshop\Actions\Orders;

use App\Domains\Bookshop\Models\BookshopCheckout;
use App\Domains\Bookshop\Models\Order;
use App\Domains\Bookshop\Models\OrderItem;
use Illuminate\Support\Str;

/**
 * COMMERCE_PARITY_PLAN P8: the receipt by SMS link. A paid checkout gets a
 * short private token (16 characters — short enough for an SMS, too long to
 * guess); `/shop/r/{token}` shows the receipt without signing in: who sold
 * each order, its lines and totals, the GST line and TIN where the shop is
 * registered. Never the address, the phone or the messages — a link can be
 * forwarded, so it carries what a paper receipt carries and nothing more.
 */
class CheckoutReceiptAction
{
    public function link(BookshopCheckout $checkout): string
    {
        if ($checkout->receipt_token === null) {
            do {
                $token = Str::lower(Str::random(16));
            } while (BookshopCheckout::query()->where('receipt_token', $token)->exists());
            $checkout->forceFill(['receipt_token' => $token])->save();
        }

        return route('public.shop.receipt', $checkout->receipt_token);
    }

    /**
     * @return array<string, mixed>|null the receipt, or null for an unknown token or a checkout not paid
     */
    public function find(string $token): ?array
    {
        $checkout = BookshopCheckout::query()->where('receipt_token', $token)->whereNotNull('paid_at')
            ->with(['orders' => fn ($q) => $q->orderBy('id'), 'orders.vendor', 'orders.items'])->first();
        if ($checkout === null) {
            return null;
        }

        return [
            'number' => $checkout->number,
            'paid_at' => $checkout->paid_at?->format('Y-m-d H:i'),
            'payment_method' => $checkout->payment_method->value,
            'currency' => $checkout->currency,
            'discount' => (string) $checkout->discount,
            'total' => (string) $checkout->total,
            'orders' => $checkout->orders->map(fn (Order $o) => [
                'number' => $o->number,
                'status' => $o->status->value,
                'shop' => $o->vendor?->legal_name ?: $o->vendor?->name,
                'tax_shown' => (bool) $o->tax_shown,
                'vendor_tin' => $o->tax_shown ? $o->vendor_tin : null,
                'items' => $o->items->map(fn (OrderItem $i) => [
                    'title' => $i->title, 'variant' => $i->variant_name, 'quantity' => (int) $i->quantity,
                    'unit_price' => (string) $i->unit_price, 'line_total' => (string) $i->line_total,
                ])->values()->all(),
                'goods' => (string) $o->subtotal,
                'discount' => (string) $o->discount,
                'delivery' => (string) $o->delivery_fee,
                'delivery_name' => $o->delivery_name,
                'carrier_paid' => (bool) $o->delivery_carrier_paid,
                'tax' => (string) $o->tax,
                'total' => (string) $o->total,
            ])->values()->all(),
        ];
    }
}
