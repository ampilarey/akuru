<?php

namespace App\Domains\Library\Actions;

use App\Domains\Commerce\Actions\DebitWalletAction;
use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Commerce\Actions\ResolveDiscountAction;
use App\Domains\Finance\Actions\InitiatePayablePaymentAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
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
            throw ValidationException::withMessages(['item' => 'This item is not for sale.']);
        }

        $access = app(ResolveLibraryAccessAction::class)->execute($item, $userId);
        if ($access['can_read']) {
            throw ValidationException::withMessages(['item' => 'You already have access to this item.']);
        }

        $amount = (float) $item->price;
        $resolvedDiscount = null;
        if ($discountCode !== null && trim($discountCode) !== '') {
            $resolvedDiscount = app(ResolveDiscountAction::class)
                ->execute($discountCode, $userId, $amount, $payWithWallet);
            $amount = $resolvedDiscount['final_amount'];
        }

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
        }

        // A fully discounted order has nothing left to pay — complete it
        // immediately regardless of the chosen payment path.
        if ($payWithWallet || $amount <= 0) {
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
            // L6: wallet sales accrue the writer's earning too — wallet is
            // payment, not discount (§16.2).
            app(RecordWriterEarningForPurchaseAction::class)->execute($purchase->id);

            return [
                'purchase' => $purchase->refresh(),
                'redirect_url' => null,
                'error' => null,
                'paid_with_wallet' => true,
            ];
        }

        $initiated = app(InitiatePayablePaymentAction::class)->execute(
            'library_item',
            $item->id,
            $userId,
            $amount,
            $item->currency ?: 'MVR',
            $returnUrl,
        );
        $purchase->payment_id = $initiated['payment']->id;
        $purchase->save();

        return [
            'purchase' => $purchase->refresh(),
            'redirect_url' => $initiated['redirect_url'],
            'error' => $initiated['error'],
            'paid_with_wallet' => false,
        ];
    }
}
