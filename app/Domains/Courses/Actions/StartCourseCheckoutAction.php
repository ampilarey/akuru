<?php

namespace App\Domains\Courses\Actions;

use App\Domains\Commerce\Actions\DebitWalletAction;
use App\Domains\Commerce\Actions\RecordDiscountRedemptionAction;
use App\Domains\Commerce\Actions\ResolveDiscountAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Finance\Actions\InitiatePayablePaymentAction;
use App\Domains\Offerings\Actions\DefaultSelfLearningOfferingAction;
use App\Domains\Offerings\Actions\ResolveOfferingPriceOverrideAction;
use Illuminate\Support\Facades\DB;

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
        // P4.4 (SPEC §49): the offering may override the course price —
        // an override of 0 makes that offering free. Falls back to the
        // course fee (the same money the legacy checkout charges).
        $override = $offeringId !== null
            ? app(ResolveOfferingPriceOverrideAction::class)->execute($offeringId)
            : (app(DefaultSelfLearningOfferingAction::class)->execute($courseId)['price_override'] ?? null);
        $fee = $override !== null ? (float) $override : (float) ($course->registration_fee_amount ?: $course->fee ?: 0);

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

        // W1: what the wallet pays for is written all at once or not at all.
        // The enrolment (with its seat), the code's redemption and the debit
        // were three writes in a row, so a debit refused for too small a
        // balance left a pending enrolment holding a seat, and a redemption
        // counting against the code's limits (KNOWN_ISSUES). The Bookstore's
        // checkout already holds its writes in one transaction.
        if ($payWithWallet || $amount <= 0) {
            return DB::transaction(function () use ($userId, $courseId, $offeringId, $course, $amount, $resolvedDiscount): array {
                [$enrollment, $held] = $this->openEnrollment($userId, $courseId, $offeringId, $resolvedDiscount);
                if ($held) {
                    return $this->alreadyHeld($enrollment);
                }
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
            });
        }

        [$enrollment, $held] = $this->openEnrollment($userId, $courseId, $offeringId, $resolvedDiscount);
        if ($held) {
            return $this->alreadyHeld($enrollment);
        }

        // SPEC §38: the payment row carries the student, the course and the
        // offering. It carried none of them — and `PaymentService::
        // recordPaymentCompletedFunnel()` resolves the course from
        // `payment->course_id` or from `payment_items`, neither of which an
        // engine payment has, so every course bought through this path
        // recorded **no `payment_completed` funnel event at all**.
        $initiated = app(InitiatePayablePaymentAction::class)->execute(
            'course_enrollment',
            $enrollment->id,
            $userId,
            $amount,
            'MVR',
            null,
            [
                'course_id' => $course->id,
                'course_offering_id' => $enrollment->course_offering_id,
                'unified_student_id' => $enrollment->unified_student_id,
                'metadata' => [
                    'source' => 'course_engine_checkout',
                    'course_title' => $course->title,
                    'list_price' => $fee,
                    'discount_code' => $resolvedDiscount['discount_code']->code ?? null,
                ],
            ],
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

    /**
     * The enrolment, pending, and the code's redemption. The idempotent
     * creator may hand back one already held (paid, or free): that is never
     * charged for again, and the second value says so.
     *
     * @param  array<string, mixed>|null  $resolvedDiscount
     * @return array{0: CourseEnrollment, 1: bool}
     */
    private function openEnrollment(int $userId, int $courseId, ?int $offeringId, ?array $resolvedDiscount): array
    {
        $enrollment = app(EnrollSelfLearningAction::class)->execute($userId, $courseId, $offeringId, [
            'status' => 'pending',
            'enrollment_type' => 'paid',
            'enrolled_at' => null,
            'payment_status' => 'pending',
        ]);
        if ($enrollment->payment_status === 'not_required' || $enrollment->payment_status === 'confirmed') {
            return [$enrollment, true];
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

        return [$enrollment, false];
    }

    /**
     * @return array{enrollment: CourseEnrollment, redirect_url: null, error: null, paid_with_wallet: false, amount: float}
     */
    private function alreadyHeld(CourseEnrollment $enrollment): array
    {
        return [
            'enrollment' => $enrollment,
            'redirect_url' => null,
            'error' => null,
            'paid_with_wallet' => false,
            'amount' => 0.0,
        ];
    }
}
