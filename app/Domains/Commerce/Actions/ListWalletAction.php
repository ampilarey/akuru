<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\Wallet;

class ListWalletAction
{
    /**
     * @return array{balance: string, currency: string, transactions: list<array<string, mixed>>}
     */
    public function execute(int $userId): array
    {
        $wallet = Wallet::query()->with('transactions')->where('user_id', $userId)->first();

        return [
            'balance' => (string) ($wallet?->balance ?? '0.00'),
            'currency' => $wallet?->currency ?? 'MVR',
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
