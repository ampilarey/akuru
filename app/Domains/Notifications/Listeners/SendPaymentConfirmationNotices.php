<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\Finance\DTOs\PaymentNoticeData;
use App\Domains\Finance\Events\PaymentNoticeReady;
use App\Domains\Identity\Actions\ListAdminMobileNumbersAction;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Mail\AdminNewEnrollmentMail;
use App\Mail\EnrollmentConfirmedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tell everyone who needs telling that a payment was confirmed.
 *
 * This is SPEC §41's worked example, finally arranged the way it asks:
 *
 *   > Cross-domain side effects must use events/listeners.
 *   >  - Enrollment should dispatch an enrollment-created event.
 *   >  - **Notifications should listen to that event.**
 *   >  - Enrollment code must not directly call notification implementation
 *   >    classes.
 *
 * The route here has been: four private methods on `PaymentService`, called by
 * hand on the line after it fired its own event — which is why an admin
 * recording cash activated an enrollment and told nobody, while a card payer
 * got an email and an SMS. Then a listener in Finance, because both Mailables
 * took an Eloquent `Payment` and importing that into Notifications would have
 * been the very rule 3 violation §41 exists to prevent. Now the Mailables take
 * `PaymentNoticeData`, so nothing here knows Finance's models — only its Event
 * and its DTO, which rule 3 allows.
 *
 * **Not queued**, deliberately. A queued listener is the textbook answer, but
 * this deployment's worker is a known operator gap (KNOWN_ISSUES #8), and a
 * confirmation that silently waits for a worker nobody runs is worse than one
 * sent inline. The event is already raised after commit, so nothing here can
 * roll a payment back. Each send is individually try/caught, so one failing
 * channel never costs the others.
 */
class SendPaymentConfirmationNotices
{
    public function __construct(private SmsSenderInterface $sms) {}

    public function handle(PaymentNoticeReady $event): void
    {
        $notice = $event->notice;

        $this->sendPayerEmail($notice);
        $this->sendPayerSms($notice);
        $this->sendAdminNewEnrollmentEmail($notice);
        $this->sendAdminSms($notice);
    }

    private function sendPayerEmail(PaymentNoticeData $notice): void
    {
        if (! $notice->payerEmail) {
            return;
        }

        try {
            Mail::to($notice->payerEmail)->send(new EnrollmentConfirmedMail($notice));
        } catch (\Throwable $e) {
            Log::warning('EnrollmentConfirmedMail: failed to send', [
                'payment_id' => $notice->paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendPayerSms(PaymentNoticeData $notice): void
    {
        if (! $notice->payerMobile) {
            return;
        }

        try {
            // `courseList()` returns an empty string rather than a dangling
            // separator when a payment is not for a course, so the two
            // sentences are written out instead of interpolating a blank.
            $courses = $notice->courseList();

            $message = $courses === ''
                ? "Akuru: Payment received for {$notice->studentName}. Pending admin approval."
                : "Akuru: Payment received for {$notice->studentName} – {$courses}. Pending admin approval.";

            $this->sms->sendSms($notice->payerMobile, $message);
        } catch (\Throwable $e) {
            Log::warning('EnrollmentConfirmedSms: failed to send', [
                'payment_id' => $notice->paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendAdminNewEnrollmentEmail(PaymentNoticeData $notice): void
    {
        $adminEmail = config('mail.admin_notification_address')
            ?? config('mail.from.address');

        if (! $adminEmail) {
            return;
        }

        try {
            Mail::to($adminEmail)->queue(new AdminNewEnrollmentMail($notice));
        } catch (\Throwable $e) {
            Log::warning('AdminNewEnrollmentMail: failed to queue', [
                'payment_id' => $notice->paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * One SMS per admin per course on the payment.
     *
     * **The audience is deliberately unchanged.** This walked
     * `$payment->items`, so it has only ever fired for the legacy consolidated
     * payments; engine checkout and manual payments have no item rows and were
     * silent. That restriction was an accident of the query rather than a
     * decision — but widening it would start sending admins an SMS for every
     * engine checkout, and who gets woken up is the owner's call, not a
     * refactor's. `hasItemisedCourses` holds it exactly where it was, visibly,
     * and says on the DTO what it is waiting for.
     */
    private function sendAdminSms(PaymentNoticeData $notice): void
    {
        if ($notice->courses === [] || ! $notice->hasItemisedCourses) {
            return;
        }

        try {
            // Through Identity's own Action: resolving this used to be
            // `Identity\Models\User::role(...)` inside a Finance service.
            $admins = app(ListAdminMobileNumbersAction::class)->execute();

            if ($admins === []) {
                return;
            }

            foreach ($notice->courses as $course) {
                $message = "[Akuru] Payment confirmed: {$notice->payerName} → {$course['title']} ({$notice->amountLabel()})";

                foreach ($admins as $adminMobile) {
                    $this->sms->sendSms($adminMobile, $message);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('notifyAdminsPaymentConfirmed: failed', [
                'payment_id' => $notice->paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
