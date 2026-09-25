<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\GiftCardOrder;

/**
 * §7.7 "gift card usage" and §41 admin "gift card purchase": every gift
 * card bought through `/gift-cards`, with who bought it, for whom, whether
 * the bank confirmed it, and where the code went — masked, as the order
 * records it (§43.19). Buyers are named through the auth model from
 * config, so Commerce never imports Identity's model (rule 3).
 *
 * @return list<array<string, mixed>>
 */
class ListGiftCardOrdersAction
{
    public function execute(int $limit = 200): array
    {
        $orders = GiftCardOrder::query()->orderByDesc('id')->limit($limit)->get();

        $userModel = config('auth.providers.users.model');
        $buyers = $userModel::query()
            ->whereIn('id', $orders->pluck('user_id')->unique()->all())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        return $orders->map(fn (GiftCardOrder $order): array => [
            'id' => $order->id,
            'buyer' => $buyers->get($order->user_id)?->name ?? ('#'.$order->user_id),
            'buyer_email' => $buyers->get($order->user_id)?->email,
            'amount' => (string) $order->amount,
            'currency' => $order->currency,
            'recipient_name' => $order->recipient_name,
            'status' => $order->status,
            'delivered_via' => $order->delivered_via,
            'delivered_to' => $order->delivered_to,
            'gift_card_id' => $order->gift_card_id,
            'paid_at' => $order->paid_at?->toDateTimeString(),
            'created_at' => $order->created_at?->toDateTimeString(),
        ])->values()->all();
    }
}
