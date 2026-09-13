<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Enums\GiftCardStatus;
use App\Domains\Commerce\Models\GiftCard;
use App\Domains\Commerce\Models\Wallet;

/**
 * What the Institute owes in stored value — LIBRARY_PLAN §13.7's "gift card
 * liabilities, wallet balance liabilities", neither of which was reported
 * anywhere.
 *
 * This is an accounting figure rather than a nice-to-have. Every gift card
 * issued and every laari of wallet credit is an obligation to hand over goods
 * later, against money that was either already taken or never taken at all
 * (an admin-issued card is a gift the Institute funds itself). A school that
 * cannot say what it owes cannot close its books.
 *
 * **The number is deliberately two numbers plus a total, and the reason is the
 * one way this could be wrong.** Redemption *moves* value: `RedeemGiftCardAction`
 * zeroes the card and credits the wallet in one transaction. So a redeemed card
 * must contribute nothing, or the same money is owed twice. That is why the
 * gift-card side counts **balance, not face value**, and only for statuses that
 * can still be spent.
 *
 * Statuses that count:
 *  - `active` — issued and untouched.
 *  - `partially_used` — a remainder is still owed.
 *
 * Statuses that do not:
 *  - `redeemed` / `empty` — the value left the card (to a wallet, which the
 *    other half of this figure counts) or was spent.
 *  - `expired` / `deactivated` — no longer claimable. Whether an expired card
 *    should still be honoured is a policy question §24 leaves to the owner;
 *    until it is answered, an unclaimable card is not a liability.
 */
class ResolveStoredValueLiabilityAction
{
    /**
     * @return array{gift_cards: float, wallets: float, total: float, gift_card_count: int, wallet_count: int}
     */
    public function execute(): array
    {
        $spendable = [
            GiftCardStatus::Active->value,
            GiftCardStatus::PartiallyUsed->value,
        ];

        $cards = GiftCard::query()->whereIn('status', $spendable);

        // An expiry that has passed is not claimable even if the row has not
        // been flipped yet — the status flip happens lazily, on the next
        // redemption attempt, so the report must not wait for it.
        $cards->where(function ($query) {
            $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });

        $giftCards = round((float) $cards->sum('balance_amount'), 2);
        $wallets = round((float) Wallet::query()->sum('balance'), 2);

        return [
            'gift_cards' => $giftCards,
            'wallets' => $wallets,
            'total' => round($giftCards + $wallets, 2),
            'gift_card_count' => (clone $cards)->count(),
            'wallet_count' => Wallet::query()->where('balance', '>', 0)->count(),
        ];
    }
}
