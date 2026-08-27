<?php

namespace App\Domains\Courses\Listeners;

use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Events\PaymentRefunded;

/**
 * P4.3: the mirror of ActivateEnrollmentOnPaymentConfirmed — a FULL
 * refund takes back what the money bought. Partial refunds keep the
 * enrollment (goodwill/adjustment refunds). Covers both payment shapes:
 * engine payables (course_enrollment) and legacy PaymentItem rows.
 */
class CancelEnrollmentOnPaymentRefunded
{
    public function handle(PaymentRefunded $event): void
    {
        if (! $event->fullyRefunded) {
            return;
        }

        $payment = $event->payment;

        $enrollmentIds = $payment->getRawOriginal('payable_type') === 'course_enrollment'
            && $payment->payable_id !== null
            ? collect([(int) $payment->payable_id])
            : $payment->items()->pluck('enrollment_id')->filter()->map(fn ($id) => (int) $id);

        foreach ($enrollmentIds as $enrollmentId) {
            $enrollment = CourseEnrollment::query()->find($enrollmentId);
            if ($enrollment === null) {
                continue;
            }

            $enrollment->payment_status = 'refunded';
            if (in_array($enrollment->status, ['pending', 'active', 'approved'], true)) {
                $enrollment->status = 'cancelled';
            }
            $enrollment->save();

            // The discount slot frees — the customer kept nothing.
            app(RecordDiscountRedemptionAction::class)->releaseForRefund('course_enrollment', $enrollmentId);
        }
    }
}
