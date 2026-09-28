<?php

namespace App\Domains\Finance\Actions;

use App\Domains\Finance\Models\Payment;
use Illuminate\Database\Eloquent\Builder;

/**
 * The course payments one login made, newest first — what *My enrolments*
 * lists beside the enrolments (docs/SIGN_IN_PLAN.md ID2b). A course payment
 * is one that names its course (every checkout sets `course_id`), pays for
 * an enrolment, or carries the legacy checkout's course lines; the
 * bookstore's orders, the Library's and a school's invoices have pages of
 * their own.
 *
 * `receipt_href` is set only where the receipt opens: `confirmed` is the
 * one status the code writes for money received (`BuildPaymentReceiptAction::
 * isReceiptable`). The old portal and the old My enrolments page tested for
 * `paid` and `completed`, which `payments.status` cannot hold, so neither
 * ever offered a receipt.
 */
class ListCoursePaymentsForUserAction
{
    /**
     * @return list<array{id: int, reference: string, for: string, amount: string, currency: string, status: string, date: ?string, receipt_href: ?string}>
     */
    public function execute(int $userId): array
    {
        $receipts = app(BuildPaymentReceiptAction::class);

        return Payment::query()
            ->with(['items.course:id,title', 'course:id,title'])
            ->where('user_id', $userId)
            ->where(fn (Builder $query) => $query
                ->whereNotNull('course_id')
                ->orWhere('payable_type', 'course_enrollment')
                ->orWhereHas('items'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => (int) $payment->id,
                'reference' => (string) ($payment->local_id ?: $payment->merchant_reference ?: '#'.$payment->id),
                'for' => $this->describe($payment),
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => (string) ($payment->currency ?: 'MVR'),
                'status' => (string) $payment->status,
                'date' => $payment->created_at?->toDateString(),
                'receipt_href' => $receipts->isReceiptable($payment) ? route('payment.receipt', $payment->id, false) : null,
            ])
            ->values()
            ->all();
    }

    private function describe(Payment $payment): string
    {
        $titles = $payment->items->map(fn ($item) => $item->course?->title)->filter()->unique()->values();
        if ($titles->isEmpty() && $payment->course?->title !== null) {
            $titles->push($payment->course->title);
        }

        return $titles->isEmpty() ? (string) ($payment->metadata['course_title'] ?? '—') : $titles->implode(', ');
    }
}
