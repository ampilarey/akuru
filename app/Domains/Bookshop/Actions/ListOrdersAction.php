<?php

namespace App\Domains\Bookshop\Actions;

use App\Domains\Bookshop\Models\Order;

/**
 * Every order, for the office (BOOKSHOP_PLAN §7) and its CSV. Customers are
 * named through the auth model from config (rule 3); the phone is on the
 * order's own address snapshot.
 *
 * @return list<array<string, mixed>>
 */
class ListOrdersAction
{
    public function execute(int $limit = 500): array
    {
        $orders = Order::query()->with(['vendor', 'items', 'checkout'])->orderByDesc('id')->limit($limit)->get();

        $userModel = config('auth.providers.users.model');
        $people = $userModel::query()->whereIn('id', $orders->pluck('user_id')->unique()->all())->get(['id', 'name', 'email'])->keyBy('id');

        return $orders->map(fn (Order $o) => [
            'id' => $o->id,
            'number' => $o->number,
            'status' => $o->status->value,
            'vendor' => $o->vendor->name,
            'customer' => $people->get($o->user_id)?->name ?? ('#'.$o->user_id),
            'customer_email' => $people->get($o->user_id)?->email,
            'payment_method' => $o->checkout?->payment_method->value,
            'items' => (int) $o->items->sum('quantity'),
            'subtotal' => (string) $o->subtotal,
            'discount' => (string) $o->discount,
            'delivery' => $o->delivery_name,
            'delivery_fee' => (string) $o->delivery_fee,
            'tax' => (string) $o->tax,
            'total' => (string) $o->total,
            'currency' => $o->currency,
            'island' => trim(($o->address_snapshot['atoll'] ?? '').' '.($o->address_snapshot['island'] ?? '')),
            'placed_at' => $o->created_at?->toDateTimeString(),
            'paid_at' => $o->paid_at?->toDateTimeString(),
        ])->values()->all();
    }
}
