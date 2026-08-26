<?php

namespace App\Domains\Notifications\Providers;

use App\Domains\Academics\Events\StudentMarkedAbsent;
use App\Domains\ExamsGrades\Events\ExamResultsPublished;
use App\Domains\ExamsGrades\Events\ReportCardsPublished;
use App\Domains\Finance\Events\InvoiceIssued;
use App\Domains\Finance\Events\InvoiceReminderDue;
use App\Domains\Notifications\Contracts\PushSenderInterface;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Listeners\NotifyExamResultsPublished;
use App\Domains\Notifications\Listeners\NotifyReportCardsPublished;
use App\Domains\Notifications\Listeners\SendAbsenceSms;
use App\Domains\Notifications\Listeners\SendInvoiceGuardianNotice;
use App\Domains\Notifications\Services\LogSmsSender;
use App\Domains\Notifications\Services\NullPushSender;
use App\Domains\Notifications\Services\SmsGatewayService;
use App\Domains\Notifications\Support\LiveSms;
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
        $this->app->singleton(PushSenderInterface::class, NullPushSender::class);
    }

    public function boot(): void
    {
        Event::listen(StudentMarkedAbsent::class, SendAbsenceSms::class);
        Event::listen(ExamResultsPublished::class, NotifyExamResultsPublished::class);
        Event::listen(ReportCardsPublished::class, NotifyReportCardsPublished::class);
        Event::listen(InvoiceIssued::class, [SendInvoiceGuardianNotice::class, 'handleIssued']);
        Event::listen(InvoiceReminderDue::class, [SendInvoiceGuardianNotice::class, 'handleReminder']);
    }
}
