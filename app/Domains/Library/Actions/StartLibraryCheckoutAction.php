<?php

namespace App\Domains\Library\Actions;

use App\Domains\Finance\Actions\InitiatePayablePaymentAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use Illuminate\Validation\ValidationException;

/**
 * L3 checkout (LIBRARY_PLAN §14 MVP): pending purchase + BML redirect via
 * Finance's generic payable initiation. Nothing here grants access —
 * the grant arrives from the webhook listener (§43.5).
 */
class StartLibraryCheckoutAction
{
    /**
     * @return array{purchase: LibraryPurchase, redirect_url: ?string, error: ?string}
     */
    public function execute(string $slug, int $userId, ?string $returnUrl = null): array
    {
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

        $purchase = LibraryPurchase::query()->create([
            'user_id' => $userId,
            'library_item_id' => $item->id,
            'amount' => (float) $item->price,
            'currency' => $item->currency ?: 'MVR',
            'status' => 'pending',
        ]);

        $initiated = app(InitiatePayablePaymentAction::class)->execute(
            'library_item',
            $item->id,
            $userId,
            (float) $item->price,
            $item->currency ?: 'MVR',
            $returnUrl,
        );
        $purchase->payment_id = $initiated['payment']->id;
        $purchase->save();

        return [
            'purchase' => $purchase->refresh(),
            'redirect_url' => $initiated['redirect_url'],
            'error' => $initiated['error'],
        ];
    }
}
