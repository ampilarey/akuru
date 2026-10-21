<?php

namespace App\Domains\Library\Listeners;

use App\Domains\Finance\Events\PaymentFailed;
use App\Domains\Library\Actions\NotifyLibraryUserAction;
use App\Domains\Library\Models\LibraryItem;
use App\Domains\Library\Models\LibraryPurchase;
use App\Support\Said;

/**
 * B11b (LIBRARY_PLAN §41 "payment issue", STATUS §5ua): a reader's card
 * payment for a Library item failed at the bank. The office hears which work
 * and whose, in the app (and by its own address and number when its notice
 * switches say so). Nothing is granted — access waits for PaymentConfirmed.
 */
class TellOfficeOfFailedLibraryPayment
{
    public function handle(PaymentFailed $event): void
    {
        $payment = $event->payment;
        $type = $payment->getRawOriginal('payable_type');
        if (! in_array($type, ['library_item', 'library_bundle'], true) || $payment->payable_id === null) {
            return;
        }
        if ($type === 'library_bundle') {
            // B4d: a bundle's card payment the bank refused.
            $bundle = \App\Domains\Library\Models\LibraryBundle::query()->find($payment->payable_id);
            if ($bundle !== null) {
                app(NotifyLibraryUserAction::class)->office(
                    Said::of('library_notices.payment_failed_title'),
                    Said::of('library_notices.payment_failed_body', ['title' => $bundle->title, 'name' => (string) ($payment->user?->name ?? '—'), 'amount' => number_format((float) $payment->amount, 2)]),
                    '/admin/library/bundles',
                );
            }

            return;
        }
        $purchase = LibraryPurchase::query()->where('payment_id', $payment->id)->first();
        $item = LibraryItem::query()->find($payment->payable_id);
        if ($item === null) {
            return;
        }
        app(NotifyLibraryUserAction::class)->office(
            Said::of('library_notices.payment_failed_title'),
            Said::of('library_notices.payment_failed_body', [
                'title' => $item->title,
                'name' => (string) ($payment->user?->name ?? '—'),
                'amount' => number_format((float) ($purchase?->amount ?? $payment->amount), 2),
            ]),
            '/admin/library',
        );
    }
}
