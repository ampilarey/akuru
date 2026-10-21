<?php

namespace App\Domains\Library\Listeners;

use App\Domains\Finance\Events\PaymentRefunded;
use App\Domains\Library\Models\LibraryAccessGrant;
use App\Domains\Library\Models\LibraryPurchase;
use App\Domains\Library\Models\WriterEarning;

/**
 * B4d (STATUS §5un): a bundle refunded in full takes back every work it
 * granted and every earning it accrued (§43.7), as a single work's refund
 * does. A partial refund keeps access, as there.
 */
class RevokeLibraryBundleOnPaymentRefunded
{
    public function handle(PaymentRefunded $event): void
    {
        $payment = $event->payment;
        if (! $event->fullyRefunded || $payment->getRawOriginal('payable_type') !== 'library_bundle') {
            return;
        }
        $purchases = LibraryPurchase::query()->where('payment_id', $payment->id)->get();
        $ids = $purchases->pluck('id')->all();
        LibraryPurchase::query()->whereIn('id', $ids)->update(['status' => 'refunded']);
        WriterEarning::query()->whereIn('library_purchase_id', $ids)->update(['status' => 'refunded']);
        LibraryAccessGrant::query()->where('user_id', $payment->user_id)->where('source_type', 'purchase')->whereIn('source_id', $ids)
            ->update(['status' => 'revoked', 'ends_at' => now()]);
    }
}
