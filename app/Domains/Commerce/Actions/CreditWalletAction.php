<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\Wallet;
use App\Domains\Commerce\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rule 12 / §43.20: the ONLY way money enters a wallet. Transaction +
 * lockForUpdate + append-only ledger row carrying before/after balances.
 */
class CreditWalletAction
{
    public function execute(
        int $userId,
        float $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $description = null,
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Credit amount must be positive.']);
        }

        return DB::transaction(function () use ($userId, $amount, $sourceType, $sourceId, $description) {
            $wallet = Wallet::query()->firstOrCreate(['user_id' => $userId]);
            $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            $before = (float) $wallet->balance;
            $after = round($before + $amount, 2);
            $wallet->balance = $after;
            $wallet->save();

            return WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'type' => 'credit',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'amount' => round($amount, 2),
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
            ]);
        });
    }
}
