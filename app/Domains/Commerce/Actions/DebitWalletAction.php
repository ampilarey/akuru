<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\Wallet;
use App\Domains\Commerce\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rule 12 / §43.20: the ONLY way money leaves a wallet. Refuses overdrafts
 * loudly; the ledger row is append-only.
 */
class DebitWalletAction
{
    public function execute(
        int $userId,
        float $amount,
        string $sourceType,
        ?int $sourceId = null,
        ?string $description = null,
    ): WalletTransaction {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Debit amount must be positive.']);
        }

        return DB::transaction(function () use ($userId, $amount, $sourceType, $sourceId, $description) {
            $wallet = Wallet::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();
            $before = $wallet !== null ? (float) $wallet->balance : 0.0;
            if ($wallet === null || $before + 1e-9 < $amount) {
                throw ValidationException::withMessages(['amount' => 'Insufficient wallet balance.']);
            }

            $after = round($before - $amount, 2);
            $wallet->balance = $after;
            $wallet->save();

            return WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'type' => 'debit',
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
