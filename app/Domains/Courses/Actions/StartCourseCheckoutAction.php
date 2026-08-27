<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Commerce\Actions\DebitWalletAction;
use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Commerce\Actions\ResolveDiscountAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Actions\InitiatePayablePaymentAction;

/**
 * Phase 4 slice 1: the ENGINE path for paid enrollment, adopting Commerce
 * (the L4 handoff). The fee charged is registration_fee_amount, falling
 * back to fee — the same money the legacy public checkout charges, so the
 * two paths can never disagree on price. Rule 12: BML access arrives only
 * via the PaymentConfirmed webhook listener; the wallet is internal money
 * and activates immediately; a discount reduces the price.
 *
 * The legacy public-site BML checkout is UNTOUCHED (§7 standing risk rule:
 * both paths stay verified until the legacy one retires).
 */
class StartCourseCheckoutAction
{
    /**
     * @return array{enrollment: CourseEnrollment, redirect_url: ?string, error: ?string, paid_with_wallet: bool, amount: float}
     */
    public function execute(
        int $userId,
        int $courseId,
        ?int $offeringId = null,
        ?string $discountCode = null,
        bool $payWithWallet = false,
    ): array {
        $course = Course::query()->findOrFail($courseId);
        $fee = (float) ($course->registration_fee_amount ?: $course->fee ?: 0);

        if ($fee <= 0) {
            $enrollment = app(EnrollSelfLearningAction::class)->execute($userId, $courseId, $offeringId);

            return [
                'enrollment' => $enrollment,
                'redirect_url' => null,
                'error' => null,
                'paid_with_wallet' => false,
                'amount' => 0.0,
            ];
        }

        $amount = $fee;
        $resolvedDiscount = null;
        if ($discountCode !== null && trim($discountCode) !== '') {
            $resolvedDiscount = app(ResolveDiscountAction::class)
                ->execute($discountCode, $userId, $amount, $payWithWallet);
            $amount = $resolvedDiscount['final_amount'];
        }

        $enrollment = app(EnrollSelfLearningAction::class)->execute($userId, $courseId, $offeringId, [
            'status' => 'pending',
            'enrollment_type' => 'paid',
            'enrolled_at' => null,
            'payment_status' => 'pending',
        ]);
        // The idempotent creator may hand back an existing live enrollment —
        // never charge for something already held.
        if ($enrollment->payment_status === 'not_required' || $enrollment->payment_status === 'confirmed') {
            return [
                'enrollment' => $enrollment,
                'redirect_url' => null,
                'error' => null,
                'paid_with_wallet' => false,
                'amount' => 0.0,
            ];
        }

        if ($resolvedDiscount !== null) {
            app(RecordDiscountRedemptionAction::class)->execute(
                $resolvedDiscount['discount_code']->id,
                $userId,
                'course_enrollment',
                $enrollment->id,
                $resolvedDiscount['amount_discounted'],
            );
        }

        if ($payWithWallet || $amount <= 0) {
            if ($amount > 0) {
                app(DebitWalletAction::class)->execute(
                    $userId,
                    $amount,
                    'purchase',
                    $enrollment->id,
                    'Course: '.$course->title,
                );
            }
            app(ActivatePaidEnrollmentAction::class)->execute($enrollment->id);
            app(RecordDiscountRedemptionAction::class)->transition('course_enrollment', $enrollment->id, 'confirmed');

            return [
                'enrollment' => $enrollment->refresh(),
                'redirect_url' => null,
                'error' => null,
                'paid_with_wallet' => true,
                'amount' => $amount,
            ];
        }

        $initiated = app(InitiatePayablePaymentAction::class)->execute(
            'course_enrollment',
            $enrollment->id,
            $userId,
            $amount,
            'MVR',
        );
        $enrollment->payment_id = $initiated['payment']->id;
        $enrollment->save();

        return [
            'enrollment' => $enrollment->refresh(),
            'redirect_url' => $initiated['redirect_url'],
            'error' => $initiated['error'],
            'paid_with_wallet' => false,
            'amount' => $amount,
        ];
    }
}
