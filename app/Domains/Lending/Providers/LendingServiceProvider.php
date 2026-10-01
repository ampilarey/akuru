<?php

namespace App\Domains\Lending\Providers;

use Illuminate\Support\ServiceProvider;

/** LENDING_AND_USED_BOOKS_PLAN L1. Nothing to bind yet; L2 registers the reminder command here. */
class LendingServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}
}
