<?php

namespace App\Domains\Library\Listeners;

use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Library\Actions\Blog\RecordTipPaidAction;
use App\Domains\Library\Models\LibraryTip;

/**
 * BLOG_PLAN BL6a (rule 12): BML's webhook confirmed a tip's payment — the
 * only way a card tip is paid and earns. The return URL grants nothing.
 */
class RecordTipOnPaymentConfirmed
{
    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;
        if ($payment->getRawOriginal('payable_type') !== 'library_tip' || $payment->payable_id === null) {
            return;
        }
        $tip = LibraryTip::query()->whereKey((int) $payment->payable_id)->where('payment_id', $payment->id)->first();
        if ($tip !== null) {
            app(RecordTipPaidAction::class)->execute($tip->id);
        }
    }
}
