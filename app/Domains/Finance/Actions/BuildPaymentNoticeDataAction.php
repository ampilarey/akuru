<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\DTOs\PaymentNoticeData;
use App\Domains\Finance\Models\Payment;

/**
 * Turn a confirmed payment into the plain values its notices need.
 *
 * **This is the one place that answers "which courses is this payment for?"**,
 * and it exists because that question used to be answered in three places at
 * once — the SMS body, the confirmation email's Course/Status table, and the
 * admin email's subject line — each by reaching for `$payment->items`.
 *
 * Only the legacy consolidated payments have item rows. Engine checkout
 * payments carry `payable_type = course_enrollment` and no items; manual
 * payments (`RecordManualPaymentAction`) carry `course_id` and no items. So for
 * both of those all three notices rendered nothing at all: the SMS read
 * *"Payment received for Yusuf **– .**"*, and the email showed an empty table
 * under a heading saying "Payment Received".
 *
 * Three shapes, in the order they are trusted:
 *
 *  1. **`items`** — the legacy consolidated payments, which can hold several
 *     courses in one payment, so this stays first and returns all of them.
 *  2. **`payable`** — an engine checkout payment points at the enrollment
 *     itself, which knows both its course and whether it is active yet.
 *  3. **`course_id`** — a manual payment records the course as a context
 *     column (SPEC §38), with no enrollment to ask about status.
 *
 * No Courses model is named here: titles and statuses are read through
 * relations Finance already owns on its own models, so this adds no
 * cross-domain import (rule 3).
 */
class BuildPaymentNoticeDataAction
{
    public function execute(Payment $payment): PaymentNoticeData
    {
        $payment->loadMissing(['user', 'student', 'items.course', 'items.enrollment', 'payable', 'course']);

        $user = $payment->user;

        return new PaymentNoticeData(
            paymentId: (int) $payment->id,
            payerName: (string) ($user?->name ?? 'Parent/Guardian'),
            payerEmail: $this->payerEmail($payment),
            payerMobile: $user?->contacts()->where('type', 'mobile')->value('value'),
            studentName: (string) ($payment->student?->full_name ?? $user?->name ?? 'Student'),
            courses: $this->courses($payment),
            amount: (float) ($payment->amount ?? 0),
            currency: (string) ($payment->currency ?? 'MVR'),
            reference: $payment->merchant_reference,
            localId: $payment->local_id,
            paidAtLabel: ($payment->paid_at ?? $payment->created_at ?? now())->format('d M Y, H:i'),
            receiptUrl: route('payment.receipt', $payment, false),
            hasItemisedCourses: $payment->items->isNotEmpty(),
        );
    }

    /**
     * Verified contact, then the account address, then any email contact.
     *
     * Carried over from `PaymentService` unchanged: a parent who has not
     * verified an address yet still needs their confirmation.
     */
    private function payerEmail(Payment $payment): ?string
    {
        $user = $payment->user;

        if (! $user) {
            return null;
        }

        return $user->contacts()->where('type', 'email')->whereNotNull('verified_at')->value('value')
            ?? $user->email
            ?? $user->contacts()->where('type', 'email')->value('value');
    }

    /**
     * @return list<array{title: string, active: bool}>
     */
    private function courses(Payment $payment): array
    {
        // 1. Legacy consolidated payments: one payment, several courses.
        $fromItems = [];
        foreach ($payment->items as $item) {
            $title = $item->course?->title;
            if ($title) {
                $fromItems[] = [
                    'title' => (string) $title,
                    'active' => $item->enrollment?->status === 'active',
                ];
            }
        }

        if ($fromItems !== []) {
            return $fromItems;
        }

        // 2. Engine checkout: the payment points straight at the enrollment,
        //    which knows its course and whether it has been activated.
        $enrollment = $payment->getRawOriginal('payable_type') === 'course_enrollment'
            ? $payment->payable
            : null;

        if ($enrollment) {
            $title = $enrollment->course?->title;
            if ($title) {
                return [['title' => (string) $title, 'active' => $enrollment->status === 'active']];
            }
        }

        // 3. Manual payment: the course is a §38 context column, and there is
        //    no enrollment here to ask about status.
        $title = $payment->course?->title;

        return $title ? [['title' => (string) $title, 'active' => false]] : [];
    }
}
