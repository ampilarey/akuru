<?php

namespace App\Domains\Finance\Listeners;

use App\Domains\Finance\Actions\BuildPaymentNoticeDataAction;
use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Finance\Events\PaymentNoticeReady;
use App\Domains\Finance\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turn a confirmed payment into the values its notices need, and announce that
 * they are ready. **Sends nothing itself.**
 *
 * This is the Finance half of SPEC §41's worked example. Finance owns
 * `Payment`, so Finance is what reads it; Notifications owns telling people
 * things, so Notifications is what sends. The two meet at
 * `PaymentNoticeReady`, which carries a DTO — rule 3 lets a domain's Events and
 * DTOs cross a boundary, and not its Models.
 *
 * An earlier slice put the sending here, because both Mailables took a
 * `Payment` and a Notifications listener would have had to import it. Giving
 * the Mailables the DTO removed that obstacle, and the listener has moved where
 * §41 asked for it.
 *
 * **Why after commit.** `PaymentConfirmed` fires *inside* the payment
 * transaction on purpose: domains grant access by listening, so a failed
 * activation rolls back with the money. Notifications must not share that
 * property — an SMTP timeout is not a reason to un-confirm a payment. So the
 * notice event is raised from `DB::afterCommit()`, which defers inside a
 * transaction and runs immediately outside one.
 */
class RaisePaymentNoticeReady
{
    public function handle(PaymentConfirmed $event): void
    {
        $paymentId = (int) $event->payment->id;

        DB::afterCommit(function () use ($paymentId) {
            try {
                // Re-read rather than closing over the instance: the listeners
                // that ran inside the transaction activate enrollments, and the
                // notices should describe the committed state.
                $payment = Payment::query()->find($paymentId);

                if ($payment === null) {
                    return;
                }

                event(new PaymentNoticeReady(
                    app(BuildPaymentNoticeDataAction::class)->execute($payment)
                ));
            } catch (\Throwable $e) {
                // A payment that cannot be described is not a payment that
                // should be un-confirmed. It is already committed by here.
                Log::warning('PaymentNoticeReady: could not build notice data', [
                    'payment_id' => $paymentId,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
