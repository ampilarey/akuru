<?php

namespace App\Domains\Finance\DTOs;

/**
 * Everything a confirmation notice needs about a payment, as plain values.
 *
 * It exists for two reasons, and the second one is the reason it is a DTO
 * rather than a handful of extra arguments.
 *
 * **It lets the notices leave Finance.** SPEC §41 says Notifications should
 * listen for the event and send; the listener was stuck in Finance because
 * `PaymentConfirmed` carries an Eloquent `Payment` and both Mailables took one,
 * so a Notifications listener would have had to import `Finance\Models\Payment`
 * — the exact rule 3 violation §41 is about. Rule 3 permits a domain's DTOs
 * across the boundary, and this is one.
 *
 * **It gives "which courses is this payment for?" a single owner.** That
 * question was answered independently in three places — the SMS body, the
 * confirmation email's table, the admin email's subject — and all three asked
 * `$payment->items`. Only the legacy consolidated payments have item rows.
 * Engine checkout payments carry `payable_type = course_enrollment`, manual
 * payments carry `course_id`, and **neither has a single item**, so all three
 * rendered nothing: the SMS read "… for Yusuf – ." and the email showed a
 * Course/Status table with no rows under a heading that said "Payment
 * Received".
 *
 * That was live for every engine checkout payer, not only the manual ones, and
 * `BuildPaymentNoticeDataAction` is now the one place that resolves it.
 */
final class PaymentNoticeData
{
    /**
     * @param  list<array{title: string, active: bool}>  $courses  Empty is legitimate:
     *                                                             a payment need not be for a course at all. Views must
     *                                                             render that case rather than an empty table.
     */
    public function __construct(
        public readonly int $paymentId,
        public readonly string $payerName,
        public readonly ?string $payerEmail,
        public readonly ?string $payerMobile,
        public readonly string $studentName,
        public readonly array $courses,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $reference,
        public readonly ?string $localId,
        public readonly string $paidAtLabel,
        public readonly ?string $receiptUrl,
        /**
         * Whether the payment carries `payment_items` rows.
         *
         * This is storage shape, and it does not belong in a notice DTO on
         * merit. It is here for one narrow reason, with an expiry: the admin
         * SMS has only ever fired for payments with item rows, because it
         * looped over them. That restriction was an accident of the query, not
         * a decision — but widening it starts sending admins messages they have
         * never had, for every engine checkout, and who gets woken up at night
         * is the owner's call rather than a refactor's.
         *
         * So the audience is held exactly where it was, visibly, instead of
         * changing as a side effect. Delete this field and the check that reads
         * it once someone decides.
         */
        public readonly bool $hasItemisedCourses = false,
    ) {}

    /**
     * The course titles as one human-readable phrase, for an SMS or a subject
     * line. Empty string when there are none, so callers can test for it
     * rather than discovering a dangling separator in a message.
     */
    public function courseList(): string
    {
        return implode(', ', array_column($this->courses, 'title'));
    }

    public function amountLabel(): string
    {
        return number_format($this->amount, 2).' '.$this->currency;
    }
}
