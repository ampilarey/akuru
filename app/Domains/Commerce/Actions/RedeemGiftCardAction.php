<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\GiftCard;
use App\Domains\Commerce\Models\GiftCardTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Redemption moves the card's full remaining balance onto the redeemer's
 * wallet (gift cards are a PAYMENT method — rule 12/§43.13; the wallet then
 * pays). MVP redeems the whole balance in one step — partial redemption is
 * a recorded deferral, the §35.6 statuses already allow it.
 */
class RedeemGiftCardAction
{
    /**
     * @return array{gift_card: GiftCard, credited: float}
     */
    public function execute(int $userId, string $plainCode): array
    {
        return DB::transaction(function () use ($userId, $plainCode) {
            $card = GiftCard::query()
                ->where('code_hash', hash('sha256', trim($plainCode)))
                ->lockForUpdate()
                ->first();

            if ($card === null || $card->status?->value !== 'active') {
                throw ValidationException::withMessages(['code' => 'Gift card not found or no longer active.']);
            }
            if ($card->expires_at !== null && $card->expires_at->isPast()) {
                $card->status = 'expired';
                $card->save();
                throw ValidationException::withMessages(['code' => 'Gift card has expired.']);
            }
            $balance = (float) $card->balance_amount;
            if ($balance <= 0) {
                throw ValidationException::withMessages(['code' => 'Gift card has no remaining balance.']);
            }

            GiftCardTransaction::query()->create([
                'gift_card_id' => $card->id,
                'user_id' => $userId,
                'type' => 'redeem',
                'amount' => $balance,
            ]);
            $card->balance_amount = 0;
            $card->status = 'redeemed';
            $card->save();

            app(CreditWalletAction::class)->execute(
                $userId,
                $balance,
                'gift_card',
                $card->id,
                'Gift card redemption',
            );

            return ['gift_card' => $card->refresh(), 'credited' => $balance];
        });
    }
}
