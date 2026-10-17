<?php

namespace App\Domains\Library\Listeners;

use App\Domains\Finance\Events\PaymentRefunded;
use App\Domains\Library\Actions\Blog\ReverseTipAction;
use App\Domains\Library\Models\LibraryTip;

/**
 * BLOG_PLAN BL6a (§7.1): a tip's payment was fully refunded — the tip is
 * reversed with a new row, and its earning clawed back as a sale's is.
 */
class ReverseTipOnPaymentRefunded
{
    public function handle(PaymentRefunded $event): void
    {
        $payment = $event->payment;
        if (! $event->fullyRefunded || $payment->getRawOriginal('payable_type') !== 'library_tip' || $payment->payable_id === null) {
            return;
        }
        $tip = LibraryTip::query()->whereKey((int) $payment->payable_id)->where('payment_id', $payment->id)->first();
        if ($tip !== null) {
            app(ReverseTipAction::class)->execute($tip->id);
        }
    }
}
