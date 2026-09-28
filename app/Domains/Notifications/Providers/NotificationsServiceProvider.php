<?php

namespace App\Domains\Notifications\Providers;

use App\Domains\Academics\Events\BehaviorRecordLogged;
use App\Domains\Academics\Events\StudentMarkedAbsent;
use App\Domains\Admissions\Events\FreeEnrollmentConfirmed;
use App\Domains\ExamsGrades\Events\ExamResultsPublished;
use App\Domains\ExamsGrades\Events\ReportCardsPublished;
use App\Domains\Finance\Events\InvoiceIssued;
use App\Domains\Finance\Events\InvoiceReminderDue;
use App\Domains\Finance\Events\PaymentNoticeReady;
use App\Domains\Notifications\Contracts\PushSenderInterface;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Listeners\NotifyExamResultsPublished;
use App\Domains\Notifications\Listeners\NotifyReportCardsPublished;
use App\Domains\Notifications\Listeners\SendAbsenceSms;
use App\Domains\Notifications\Listeners\SendBehaviorParentSms;
use App\Domains\Notifications\Listeners\SendFreeEnrollmentNotices;
use App\Domains\Notifications\Listeners\SendInvoiceGuardianNotice;
use App\Domains\Notifications\Listeners\SendPaymentConfirmationNotices;
use App\Domains\Notifications\Services\FcmPushSender;
use App\Domains\Notifications\Services\LogPushSender;
use App\Domains\Notifications\Services\LogSmsSender;
use App\Domains\Notifications\Services\NullPushSender;
use App\Domains\Notifications\Services\SmsGatewayService;
use App\Domains\Notifications\Support\LiveSms;
use App\Domains\Notifications\Support\PushChannel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsSenderInterface::class, function () {
            return LiveSms::allowed()
                ? $this->app->make(SmsGatewayService::class)
                : $this->app->make(LogSmsSender::class);
        });
        // Push (SPEC §50, STATUS §5jr): config/push.php picks the sender; `fcm`
        // without its project id and key file falls back to null, so a
        // half-configured host records nothing delivered rather than pretending.
        $this->app->singleton(PushSenderInterface::class, function () {
            return match (PushChannel::driver()) {
                'fcm' => new FcmPushSender((string) config('push.fcm.project_id'), (string) config('push.fcm.credentials'), (int) config('push.fcm.timeout', 5)),
                'log' => $this->app->make(LogPushSender::class),
                default => $this->app->make(NullPushSender::class),
            };
        });
    }

    public function boot(): void
    {
        Event::listen(StudentMarkedAbsent::class, SendAbsenceSms::class);
        Event::listen(BehaviorRecordLogged::class, SendBehaviorParentSms::class);
        Event::listen(ExamResultsPublished::class, NotifyExamResultsPublished::class);
        Event::listen(ReportCardsPublished::class, NotifyReportCardsPublished::class);
        Event::listen(InvoiceIssued::class, [SendInvoiceGuardianNotice::class, 'handleIssued']);
        Event::listen(InvoiceReminderDue::class, [SendInvoiceGuardianNotice::class, 'handleReminder']);

        // SPEC §41's worked example: Finance describes the confirmed payment,
        // Notifications is what tells people about it.
        Event::listen(PaymentNoticeReady::class, SendPaymentConfirmationNotices::class);
        Event::listen(FreeEnrollmentConfirmed::class, SendFreeEnrollmentNotices::class);
    }
}
