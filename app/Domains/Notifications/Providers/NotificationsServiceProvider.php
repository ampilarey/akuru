<?php

namespace App\Domains\Notifications\Providers;

use App\Domains\Academics\Events\StudentMarkedAbsent;
use App\Domains\Finance\Events\InvoiceIssued;
use App\Domains\Finance\Events\InvoiceReminderDue;
use App\Domains\Notifications\Contracts\PushSenderInterface;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Listeners\SendAbsenceSms;
use App\Domains\Notifications\Listeners\SendInvoiceGuardianNotice;
use App\Domains\Notifications\Services\NullPushSender;
use App\Domains\Notifications\Services\SmsGatewayService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SmsSenderInterface::class, SmsGatewayService::class);
        $this->app->singleton(PushSenderInterface::class, NullPushSender::class);
    }

    public function boot(): void
    {
        Event::listen(StudentMarkedAbsent::class, SendAbsenceSms::class);
        Event::listen(InvoiceIssued::class, [SendInvoiceGuardianNotice::class, 'handleIssued']);
        Event::listen(InvoiceReminderDue::class, [SendInvoiceGuardianNotice::class, 'handleReminder']);
    }
}
