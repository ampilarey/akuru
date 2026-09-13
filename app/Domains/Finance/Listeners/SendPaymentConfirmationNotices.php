<?php

namespace App\Domains\Finance\Listeners;

use App\Domains\Finance\Events\PaymentConfirmed;
use App\Domains\Finance\Models\Payment;
use App\Domains\Identity\Actions\ListAdminMobileNumbersAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Mail\AdminNewEnrollmentMail;
use App\Mail\EnrollmentConfirmedMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Everyone who needs telling when a payment is confirmed.
 *
 * SPEC §41 ends on this exact example:
 *
 *   > Cross-domain side effects must use events/listeners.
 *   >  - Enrollment should dispatch an enrollment-created event.
 *   >  - Notifications should listen to that event.
 *   >  - **Enrollment code must not directly call notification implementation
 *   >    classes.**
 *
 * These four notices used to be private methods on `PaymentService`, called by
 * hand on the line after it fired `PaymentConfirmed`. The structural objection
 * had a plain user-visible cost, and it fell on the families least likely to be
 * online: **`RecordManualPaymentAction` fires the same event** — its own
 * docblock promises "one money→access path for every kind of money" — so cash
 * handed over at the office activated the enrollment exactly like a card
 * payment, and then told nobody. The admin saw "Manual payment recorded —
 * enrollment updated"; the family saw nothing at all.
 *
 * Being a listener fixes both halves at once, and it is why this is a defect
 * fix rather than a tidy-up.
 *
 * **Why this lives in Finance rather than Notifications.** §41 says
 * "Notifications should listen to that event", and that is the better home.
 * Two things block it today, and neither is worth forcing on the way past:
 * `PaymentConfirmed` carries an Eloquent `Payment` (the house pattern for
 * cross-domain events is scalars — compare `InvoiceIssued`), and both
 * Mailables take a `Payment` in their constructor and render from it. A
 * Notifications listener would therefore have to import
 * `Finance\Models\Payment`, which is precisely the rule 3 violation §41 is
 * about. Reshaping the event would touch three existing listeners on the
 * money→access path. So the side effect becomes a listener now — which is
 * §41's actual requirement — and the remaining move is recorded in
 * KNOWN_ISSUES #24. What crosses the domain boundary here is
 * `SmsSenderInterface`, a Notifications *contract*, which is exactly the
 * mechanism rule 3 prescribes.
 *
 * **Why the body is deferred to after commit.** `PaymentConfirmed` is fired
 * *inside* the payment transaction on purpose: domains grant access by
 * listening, so a failed activation rolls back with the payment. Notifications
 * must not share that property — an SMTP timeout is not a reason to un-confirm
 * money, and a mail send inside a transaction holds it open for the length of a
 * network call. `DB::afterCommit()` defers when there is a transaction and runs
 * immediately when there is not, which is the behaviour wanted in both cases.
 *
 * **Not queued**, deliberately. A queued listener is the textbook answer, but
 * this deployment's worker is a known operator gap (KNOWN_ISSUES #8), and a
 * confirmation that silently waits for a worker nobody is running is worse than
 * one sent inline. Each send is individually try/caught, exactly as before, so
 * one failing channel never costs the others.
 */
class SendPaymentConfirmationNotices
{
    public function __construct(private SmsSenderInterface $sms) {}

    public function handle(PaymentConfirmed $event): void
    {
        $paymentId = (int) $event->payment->id;

        DB::afterCommit(function () use ($paymentId) {
            // Re-read rather than closing over the instance: the listeners that
            // run before this one inside the transaction activate enrollments,
            // and the notices should describe the committed state.
            $payment = Payment::query()
                ->with(['user', 'items.course', 'items.enrollment.course', 'student', 'course'])
                ->find($paymentId);

            if ($payment === null) {
                return;
            }

            $this->sendPayerEmail($payment);
            $this->sendPayerSms($payment);
            $this->sendAdminNewEnrollmentEmail($payment);
            $this->sendAdminSms($payment);
        });
    }

    private function sendPayerEmail(Payment $payment): void
    {
        try {
            $user = $payment->user;
            if (! $user) {
                return;
            }

            // Verified contact, then the account address, then any email
            // contact. Carried across unchanged: a parent who has not verified
            // an address yet still needs the confirmation.
            $toAddress = $user->contacts()->where('type', 'email')->whereNotNull('verified_at')->value('value')
                ?? $user->email
                ?? $user->contacts()->where('type', 'email')->value('value');

            if (! $toAddress) {
                return;
            }

            Mail::to($toAddress)->send(new EnrollmentConfirmedMail($payment));
        } catch (\Throwable $e) {
            Log::warning('EnrollmentConfirmedMail: failed to send', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendPayerSms(Payment $payment): void
    {
        try {
            $user = $payment->user;
            $mobile = $user?->contacts()->where('type', 'mobile')->value('value');
            if (! $mobile) {
                return;
            }

            $studentName = $payment->student?->first_name ?? $user->name ?? 'Student';

            // The course list came from `items` alone, which only the legacy
            // consolidated payments have. Engine and manual payments carry the
            // course in `course_id` instead, so the message read
            // "Payment received for Yusuf – . Pending admin approval." — a
            // dangling dash and nothing after it.
            //
            // It was invisible until this slice, because those payments sent
            // no SMS at all. Making them send one makes the gap legible, so it
            // is fixed here rather than shipped.
            $courses = $payment->items->map(fn ($i) => $i->course?->title)->filter()->implode(', ')
                ?: (string) ($payment->course?->title ?? '');

            $message = $courses === ''
                ? "Akuru: Payment received for {$studentName}. Pending admin approval."
                : "Akuru: Payment received for {$studentName} – {$courses}. Pending admin approval.";

            $this->sms->sendSms((string) $mobile, $message);
        } catch (\Throwable $e) {
            Log::warning('EnrollmentConfirmedSms: failed to send', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendAdminNewEnrollmentEmail(Payment $payment): void
    {
        $adminEmail = config('mail.admin_notification_address')
            ?? config('mail.from.address');

        if (! $adminEmail) {
            return;
        }

        try {
            Mail::to($adminEmail)->queue(new AdminNewEnrollmentMail($payment));
        } catch (\Throwable $e) {
            Log::warning('AdminNewEnrollmentMail: failed to queue', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * One SMS per admin per enrolled item.
     *
     * This walks `$payment->items`, so it stays silent for engine checkout
     * payments, which carry `payable_type = course_enrollment` and no item
     * rows. That asymmetry is carried across **unchanged and on purpose**:
     * widening it would start sending admins messages they have never had,
     * which is a decision about who gets woken up rather than a refactor.
     */
    private function sendAdminSms(Payment $payment): void
    {
        try {
            if ($payment->items->isEmpty()) {
                return;
            }

            // Through Identity's own Action: this used to be
            // `Identity\Models\User::role(...)` inside a Finance service, which
            // is rule 3's boundary — who counts as an admin, and where their
            // number lives, is Identity's business.
            $admins = app(ListAdminMobileNumbersAction::class)->execute();

            if ($admins === []) {
                return;
            }

            $payerName = $payment->user?->name ?? 'Unknown';
            $amount = number_format((float) ($payment->amount ?? 0), 2);
            $currency = $payment->currency ?? 'MVR';

            foreach ($payment->items as $item) {
                $enrollment = $item->enrollment;
                if (! $enrollment) {
                    continue;
                }

                $courseName = $enrollment->course?->title ?? 'Unknown';
                $message = "[Akuru] Payment confirmed: {$payerName} → {$courseName} ({$currency} {$amount})";

                foreach ($admins as $adminMobile) {
                    $this->sms->sendSms($adminMobile, $message);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('notifyAdminsPaymentConfirmed: failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
