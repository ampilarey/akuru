<?php

namespace App\Domains\Notifications\Providers;

use App\Domains\Notifications\Contracts\PushSenderInterface;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Services\NullPushSender;
use App\Domains\Notifications\Services\SmsGatewayService;
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
        //
    }
}
