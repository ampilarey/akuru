<?php

namespace App\Domains\Library\Listeners;

use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Library\Actions\AnnounceLibrarySaleAction;
use App\Domains\Library\Actions\GrantLibraryAccessAction;
use App\Domains\Library\Actions\RecordWriterEarningForPurchaseAction;
use App\Domains\Library\Models\LibraryPurchase;

/**
 * B4d (STATUS §5un): a bundle paid by card. Like a single work (§43.5,
 * rule 12), the webhook is the only path from money to the grants: every
 * purchase on the payment moves pending → paid once, each work is granted,
 * each writer's earning accrues. Idempotent.
 */
class GrantLibraryBundleOnPaymentConfirmed
{
    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;
        if ($payment->getRawOriginal('payable_type') !== 'library_bundle' || $payment->user_id === null) {
            return;
        }
        foreach (LibraryPurchase::query()->where('payment_id', $payment->id)->orderBy('id')->get() as $purchase) {
            $landed = false;
            if (in_array($purchase->status, ['pending', 'failed'], true)) {
                $purchase->forceFill(['status' => 'paid', 'purchased_at' => now()])->save();
                $landed = true;
            }
            if ($purchase->status !== 'paid') {
                continue;
            }
            app(GrantLibraryAccessAction::class)->execute((int) $payment->user_id, (int) $purchase->library_item_id, 'purchase', (int) $purchase->id);
            app(RecordWriterEarningForPurchaseAction::class)->execute((int) $purchase->id);
            if ($landed) {
                app(AnnounceLibrarySaleAction::class)->execute($purchase);
            }
        }
    }
}
