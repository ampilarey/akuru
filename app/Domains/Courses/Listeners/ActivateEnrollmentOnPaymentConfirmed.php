<?php

namespace App\Domains\Courses\Listeners;

use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Courses\Actions\ActivatePaidEnrollmentAction;
use App\Domains\Finance\Events\PaymentConfirmed;

/**
 * Phase 4: the engine enrollment path's money→access moment (rule 12 —
 * webhook only, never the return URL), the same event pattern the Library
 * uses. Only fires for payments created by StartCourseCheckoutAction
 * (payable course_enrollment); the legacy PaymentItem flow never sets that
 * payable and keeps its inline handling.
 */
class ActivateEnrollmentOnPaymentConfirmed
{
    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;
        if ($payment->getRawOriginal('payable_type') !== 'course_enrollment'
            || $payment->payable_id === null) {
            return;
        }

        app(ActivatePaidEnrollmentAction::class)->execute((int) $payment->payable_id);
        app(RecordDiscountRedemptionAction::class)->transition('course_enrollment', (int) $payment->payable_id, 'confirmed');
    }
}
