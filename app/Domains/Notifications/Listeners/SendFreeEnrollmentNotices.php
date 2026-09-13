<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\Admissions\DTOs\FreeEnrollmentNoticeData;
use App\Domains\Admissions\Events\FreeEnrollmentConfirmed;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Mail\AdminFreeEnrollmentMail;
use App\Mail\FreeEnrollmentConfirmedMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Tell the family and the office about an enrollment that needed no payment.
 *
 * The free counterpart of `SendPaymentConfirmationNotices`, and the last of the
 * enrollment notices to move. SPEC §41's example is about this exact pair:
 * enrollment dispatches, Notifications listens, and enrollment code never names
 * a Mailable. Until now `CourseRegistrationController` queued both of these
 * itself.
 *
 * Each send is individually try/caught and the failures are logged rather than
 * swallowed. The controller's versions caught `\Throwable` into an empty block
 * with the comment "non-critical", so a family that never received their
 * confirmation left no trace at all.
 */
class SendFreeEnrollmentNotices
{
    public function __construct(private SmsSenderInterface $sms) {}

    public function handle(FreeEnrollmentConfirmed $event): void
    {
        $notice = $event->notice;

        $this->sendFamilyEmail($notice);
        $this->sendFamilySms($notice);
        $this->sendAdminEmail($notice);
    }

    private function sendFamilyEmail(FreeEnrollmentNoticeData $notice): void
    {
        if (! $notice->payerEmail) {
            return;
        }

        try {
            Mail::to($notice->payerEmail)->queue(new FreeEnrollmentConfirmedMail($notice));
        } catch (\Throwable $e) {
            Log::warning('FreeEnrollmentConfirmedMail: failed to queue', [
                'enrollment_id' => $notice->enrollmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendFamilySms(FreeEnrollmentNoticeData $notice): void
    {
        if (! $notice->payerMobile) {
            return;
        }

        try {
            $this->sms->sendSms(
                $notice->payerMobile,
                "Akuru: Enrollment received for {$notice->courseTitle}. Pending approval."
            );
        } catch (\Throwable $e) {
            Log::warning('FreeEnrollmentSms: failed to send', [
                'enrollment_id' => $notice->enrollmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendAdminEmail(FreeEnrollmentNoticeData $notice): void
    {
        $adminEmail = config('mail.admin_notification_address')
            ?? config('mail.from.address');

        if (! $adminEmail) {
            return;
        }

        try {
            Mail::to($adminEmail)->queue(new AdminFreeEnrollmentMail($notice));
        } catch (\Throwable $e) {
            Log::warning('AdminFreeEnrollmentMail: failed to queue', [
                'enrollment_id' => $notice->enrollmentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
