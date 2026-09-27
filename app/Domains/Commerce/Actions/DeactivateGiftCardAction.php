<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\GiftCard;
use App\Domains\Commerce\Models\GiftCardTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * B10 (LIBRARY_PLAN §15.2): the office takes a card out of circulation —
 * a leaked code, a disputed purchase, a card issued by mistake.
 *
 * The balance is frozen, not deleted: the card's status becomes
 * `deactivated`, redemption refuses it (`RedeemGiftCardAction` accepts
 * `active` alone), and the append-only ledger (§43.20) gains a
 * `deactivate` row carrying the frozen balance and the office's reason.
 * That row is the fraud log. Only a card with money still on it can be
 * deactivated; a spent or expired one has nothing to take away.
 */
class DeactivateGiftCardAction
{
    public function execute(int $giftCardId, int $actorUserId, string $reason): GiftCard
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Say why the card is being deactivated.']);
        }

        return DB::transaction(function () use ($giftCardId, $actorUserId, $reason) {
            $card = GiftCard::query()->whereKey($giftCardId)->lockForUpdate()->first();
            if ($card === null) {
                throw ValidationException::withMessages(['gift_card' => 'No such gift card.']);
            }
            if (! in_array($card->status?->value, ['active', 'partially_used'], true)) {
                throw ValidationException::withMessages(['gift_card' => 'Only a card with money still on it can be deactivated.']);
            }

            GiftCardTransaction::query()->create([
                'gift_card_id' => $card->id,
                'user_id' => $actorUserId,
                'type' => 'deactivate',
                'amount' => $card->balance_amount,
                'note' => mb_substr($reason, 0, 500),
            ]);

            $card->status = 'deactivated';
            $card->save();

            return $card->refresh();
        });
    }
}
