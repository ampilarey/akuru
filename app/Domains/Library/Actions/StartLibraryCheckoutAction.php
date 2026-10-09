<?php

namespace App\Domains\Library\Actions;

use App\Domains\Commerce\Actions\DebitWalletAction;
use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Commerce\Actions\ResolveDiscountAction;
use App\Domains\Finance\Actions\InitiatePayablePaymentAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * L3 checkout (LIBRARY_PLAN §14 MVP): pending purchase + BML redirect via
 * Finance's generic payable initiation; the grant arrives from the webhook
 * listener (§43.5). L4: an optional discount code REDUCES the price
 * (§43.15) and the wallet can PAY in full (§43.14) — a wallet purchase is
 * internal money, so it grants immediately through the same grant action.
 */
class StartLibraryCheckoutAction
{
    /**
     * @return array{purchase: LibraryPurchase, redirect_url: ?string, error: ?string, paid_with_wallet: bool}
     */
    public function execute(
        string $slug,
        int $userId,
        ?string $returnUrl = null,
        ?string $discountCode = null,
        bool $payWithWallet = false,
    ): array {
        $item = LibraryItem::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();
        if ($item->access_type?->value !== 'paid' || (float) $item->price <= 0) {
            throw ValidationException::withMessages(['item' => __('common.library_error_not_for_sale')]);
        }

        $access = app(ResolveLibraryAccessAction::class)->execute($item, $userId);
        if ($access['can_read']) {
            throw ValidationException::withMessages(['item' => __('common.library_error_have_access')]);
        }

        $amount = (float) $item->price;
        $resolvedDiscount = null;
        $promotion = null;
        if ($discountCode !== null && trim($discountCode) !== '') {
            $resolvedDiscount = app(ResolveDiscountAction::class)
                ->execute($discountCode, $userId, $amount, $payWithWallet);
            $amount = $resolvedDiscount['final_amount'];
        } else {
            // B4 (§18): with no code typed, a live campaign covering the item
            // applies by itself — the price the page showed. A code and a
            // campaign do not stack: the code is the reader's choice to use
            // instead, on the full price.
            $promotion = app(ResolveLibraryItemPromotionAction::class)->execute($item);
            if ($promotion !== null) {
                $amount = $promotion['price'];
            }
        }

        // W1: what the wallet pays for is written all at once or not at all.
        // The purchase, its redemption and the debit were three writes in a
        // row, so a debit refused for too small a balance left the purchase
        // `pending` for good — listed in My Library — and the code's
        // redemption counting against its limits (KNOWN_ISSUES). The
        // Bookstore's checkout already holds its writes in one transaction;
        // what tells people of the sale runs once it is real, after it.
        if ($payWithWallet || $amount <= 0) {
            $purchase = DB::transaction(function () use ($userId, $item, $amount, $resolvedDiscount, $promotion): LibraryPurchase {
                $purchase = $this->openPurchase($userId, $item, $amount, $resolvedDiscount, $promotion);
                // A fully discounted order has nothing left to pay — it
                // completes the same way, without a debit.
                if ($amount > 0) {
                    app(DebitWalletAction::class)->execute(
                        $userId,
                        $amount,
                        'purchase',
                        $purchase->id,
                        'Library: '.$item->title,
                    );
                }
                $purchase->status = 'paid';
                $purchase->purchased_at = now();
                $purchase->save();
                app(GrantLibraryAccessAction::class)->execute(
                    $userId,
                    $item->id,
                    $amount > 0 ? 'wallet' : 'coupon',
                    $purchase->id,
                );
                app(RecordDiscountRedemptionAction::class)->transition('library_purchase', $purchase->id, 'confirmed');

                return $purchase;
            });
            // L6: wallet sales accrue the writer's earning too — wallet is
            // payment, not discount (§16.2). It tells the writer, so it comes
            // after the commit.
            app(RecordWriterEarningForPurchaseAction::class)->execute($purchase->id);
            // COMMERCE_PARITY_PLAN P5: the reader and the office hear of it, as a card sale does.
            app(AnnounceLibrarySaleAction::class)->execute($purchase);

            return [
                'purchase' => $purchase->refresh(),
                'redirect_url' => null,
                'error' => null,
                'paid_with_wallet' => true,
            ];
        }

        $purchase = $this->openPurchase($userId, $item, $amount, $resolvedDiscount, $promotion);

        $initiated = app(InitiatePayablePaymentAction::class)->execute(
            'library_item',
            $item->id,
            $userId,
            $amount,
            $item->currency ?: 'MVR',
            $returnUrl,
        );
        $purchase->payment_id = $initiated['payment']->id;
        // §5pn: a payment that could not start will never be paid. The
        // purchase says so, and the code's slot comes back now, as the
        // Bookstore's does, not a day later from the prune.
        if ($initiated['redirect_url'] === null) {
            $purchase->status = 'failed';
            app(RecordDiscountRedemptionAction::class)->releaseAbandoned('library_purchase', [$purchase->id]);
        }
        $purchase->save();

        return [
            'purchase' => $purchase->refresh(),
            'redirect_url' => $initiated['redirect_url'],
            'error' => $initiated['error'],
            'paid_with_wallet' => false,
        ];
    }

    /**
     * The purchase, pending, and what reduced its price: a code's redemption
     * or a campaign's.
     *
     * @param  array<string, mixed>|null  $resolvedDiscount
     * @param  array<string, mixed>|null  $promotion
     */
    private function openPurchase(int $userId, LibraryItem $item, float $amount, ?array $resolvedDiscount, ?array $promotion): LibraryPurchase
    {
        $purchase = LibraryPurchase::query()->create([
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'amount' => $amount,
            'currency' => $item->currency ?: 'MVR',
            'status' => 'pending',
        ]);

        if ($resolvedDiscount !== null) {
            app(RecordDiscountRedemptionAction::class)->execute(
                $resolvedDiscount['discount_code']->id,
                $userId,
                'library_purchase',
                $purchase->id,
                $resolvedDiscount['amount_discounted'],
            );
        } elseif ($promotion !== null) {
            app(RecordDiscountRedemptionAction::class)->forCampaign(
                $promotion['campaign_id'],
                $userId,
                'library_purchase',
                $purchase->id,
                $promotion['amount_off'],
            );
        }

        return $purchase;
    }
}
