<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Payment;

/**
 * What a receipt actually says, decided once rather than inside a Blade view.
 *
 * Two things were wrong beyond the 404 that hid them both.
 *
 * **The line items could be empty.** The view loops `$payment->items`, and an
 * engine-path payment has none — `InitiatePayablePaymentAction` creates the
 * Payment and no `payment_items` at all. A receipt that reached the browser
 * would have shown an empty table above a total, with nothing saying what the
 * money bought. With SPEC §38's `course_id` now populated, the payment itself
 * can answer, so the fallback is a real line rather than a blank.
 *
 * **The method line was wrong for every payment.** It read
 * `BML {{ $payment->provider ?? 'Card' }}`, which prints "BML bml" for a
 * gateway payment and "BML manual" for cash taken at the office — the one
 * case where it is certainly not BML. §38 separates gateway from method, so
 * the receipt now shows the gateway and the instrument as the two different
 * things they are.
 */
class BuildPaymentReceiptAction
{
    /**
     * Money received. `confirmed` is the only status the code writes for that
     * (`PaymentService` on webhook confirmation, `RecordManualPaymentAction`
     * for money taken by hand).
     *
     * `refunded` is deliberately excluded: a receipt asserts money received
     * and kept, and handing one out for money already returned is the kind of
     * document someone later waves at an office.
     */
    public function isReceiptable(Payment $payment): bool
    {
        return (string) $payment->status === 'confirmed';
    }

    /**
     * @return array{lines: list<array{description: string, student: ?string, amount: float}>, gateway: string, method: ?string, total: float, currency: string}
     */
    public function execute(Payment $payment): array
    {
        $payment->loadMissing(['user', 'student', 'items.course', 'items.enrollment', 'course']);

        $lines = [];
        foreach ($payment->items as $item) {
            $lines[] = [
                'description' => (string) ($item->course?->title ?? 'Course enrollment'),
                'student' => $item->enrollment?->student?->full_name ?? $payment->student?->full_name,
                'amount' => (float) ($item->amount ?? $payment->amount),
            ];
        }

        if ($lines === []) {
            // An engine payment carries no items. §38's `course_id` is what
            // makes this a real description instead of a blank row.
            $lines[] = [
                'description' => (string) ($payment->course?->title ?? 'Course enrollment'),
                'student' => $payment->student?->full_name,
                'amount' => (float) $payment->amount,
            ];
        }

        return [
            'lines' => $lines,
            'gateway' => $payment->provider === 'manual' ? 'Recorded at the institute' : 'BML Connect',
            'method' => $payment->payment_method?->label(),
            'total' => (float) $payment->amount,
            'currency' => (string) ($payment->currency ?: 'MVR'),
        ];
    }
}
