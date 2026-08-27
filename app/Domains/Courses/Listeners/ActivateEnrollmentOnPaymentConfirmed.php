<?php

namespace App\Domains\Courses\Listeners;

use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Courses\Actions\ActivatePaidEnrollmentAction;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Events\PaymentConfirmed;

/**
 * The single money→access moment for course enrollments (rule 12 — webhook
 * only, never the return URL), the same event pattern the Library uses.
 *
 * P4.2: covers BOTH payment shapes. Engine checkout payments carry
 * payable_type=course_enrollment; legacy public-checkout payments carry
 * PaymentItem rows instead. Either way the enrollment flips through
 * ActivatePaidEnrollmentAction — no other code activates paid enrollments.
 */
class ActivateEnrollmentOnPaymentConfirmed
{
    public function handle(PaymentConfirmed $event): void
    {
        $payment = $event->payment;

        if ($payment->getRawOriginal('payable_type') === 'course_enrollment'
            && $payment->payable_id !== null) {
            $this->activate((int) $payment->payable_id);

            return;
        }

        // Legacy consolidated payments: one payment, item rows per enrollment.
        foreach ($payment->items()->pluck('enrollment_id') as $enrollmentId) {
            if ($enrollmentId !== null
                && CourseEnrollment::query()->whereKey((int) $enrollmentId)->exists()) {
                $this->activate((int) $enrollmentId);
            }
        }
    }

    private function activate(int $enrollmentId): void
    {
        app(ActivatePaidEnrollmentAction::class)->execute($enrollmentId);
        app(RecordDiscountRedemptionAction::class)->transition('course_enrollment', $enrollmentId, 'confirmed');
    }
}
