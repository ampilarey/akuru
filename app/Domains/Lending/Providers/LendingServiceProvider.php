<?php

namespace App\Domains\Lending\Providers;

use App\Domains\Lending\Console\RemindLendingCommand;
use Illuminate\Support\ServiceProvider;

/** LENDING_AND_USED_BOOKS_PLAN L1/L2: nothing to bind; the daily reminder command (L2). */
class LendingServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RemindLendingCommand::class]);
        }
    }
}
