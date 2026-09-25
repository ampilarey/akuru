<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\GiftCardOrder;
use App\Domains\Commerce\Models\Wallet;

class ListWalletAction
{
    /**
     * @return array{balance: string, currency: string, transactions: list<array<string, mixed>>, gift_card_orders: list<array<string, mixed>>}
     */
    public function execute(int $userId): array
    {
        $wallet = Wallet::query()->with('transactions')->where('user_id', $userId)->first();

        return [
            'balance' => (string) ($wallet?->balance ?? '0.00'),
            'currency' => $wallet?->currency ?? 'MVR',
            // §15.3: the gift cards this person bought — status and where the
            // code went, never the code.
            'gift_card_orders' => GiftCardOrder::query()
                ->where('user_id', $userId)
                ->orderByDesc('id')
                ->limit(50)
                ->get()
                ->map(fn (GiftCardOrder $order) => [
                    'id' => $order->id,
                    'amount' => (string) $order->amount,
                    'currency' => $order->currency,
                    'recipient_name' => $order->recipient_name,
                    'status' => $order->status,
                    'delivered_to' => $order->delivered_to,
                    'created_at' => $order->created_at?->toDateTimeString(),
                ])
                ->values()
                ->all(),
            'transactions' => $wallet === null ? [] : $wallet->transactions
                ->take(100)
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'type' => $row->type,
                    'source_type' => $row->source_type,
                    'amount' => (string) $row->amount,
                    'balance_after' => (string) $row->balance_after,
                    'description' => $row->description,
                    'created_at' => $row->created_at?->toDateTimeString(),
                ])
                ->values()
                ->all(),
        ];
    }
}
