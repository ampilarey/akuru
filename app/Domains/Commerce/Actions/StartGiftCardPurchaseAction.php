<?php

namespace App\Domains\Commerce\Actions;

use App\Domains\Commerce\Models\GiftCardOrder;
use App\Domains\Finance\Actions\InitiatePayablePaymentAction;
use Illuminate\Validation\ValidationException;

/**
 * §15.3: amount → recipient → message → BML. The card does not exist yet;
 * `IssueGiftCardOnPaymentConfirmed` makes it when the webhook says the
 * money arrived (§43.5 — never the return URL).
 *
 * §15.4, on purpose and by construction: this action takes no discount
 * code and offers no wallet payment. A gift card IS money; discounting one
 * mints it at a loss, and buying one from a wallet turns stored value into
 * a transferable code. `DiscountsNeverBuyGiftCardsTest` pins that this file
 * never resolves a discount.
 */
class StartGiftCardPurchaseAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{order: GiftCardOrder, redirect_url: ?string, error: ?string}
     */
    public function execute(int $userId, array $data, ?string $returnUrl = null): array
    {
        $amount = (float) ($data['amount'] ?? 0);
        $min = (float) config('library.gift_cards.min', 50);
        $max = (float) config('library.gift_cards.max', 5000);
        if ($amount < $min || $amount > $max || abs($amount - round($amount)) > 0.0001) {
            throw ValidationException::withMessages([
                'amount' => sprintf('Choose a whole amount between MVR %s and MVR %s.', number_format($min), number_format($max)),
            ]);
        }

        $name = trim((string) ($data['recipient_name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessages(['recipient_name' => 'Say who the gift card is for.']);
        }
        $email = trim((string) ($data['recipient_email'] ?? '')) ?: null;
        $mobile = preg_replace('/\s+/', '', (string) ($data['recipient_mobile'] ?? '')) ?: null;
        if ($email === null && $mobile === null) {
            throw ValidationException::withMessages([
                'recipient_email' => 'Give an email address or a mobile number to send the code to.',
            ]);
        }

        $order = GiftCardOrder::query()->create([
            'user_id' => $userId,
            'amount' => $amount,
            'currency' => 'MVR',
            'recipient_name' => $name,
            'recipient_email' => $email,
            'recipient_mobile' => $mobile,
            'message' => trim((string) ($data['message'] ?? '')) ?: null,
            'status' => 'pending',
        ]);

        $initiated = app(InitiatePayablePaymentAction::class)->execute(
            'gift_card_order',
            $order->id,
            $userId,
            $amount,
            'MVR',
            $returnUrl,
        );
        $order->payment_id = $initiated['payment']->id;
        if ($initiated['redirect_url'] === null) {
            $order->status = 'failed';
        }
        $order->save();

        return [
            'order' => $order->refresh(),
            'redirect_url' => $initiated['redirect_url'],
            'error' => $initiated['error'],
        ];
    }
}
