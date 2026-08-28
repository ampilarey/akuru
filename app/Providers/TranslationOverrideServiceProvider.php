<?php

namespace App\Providers;

use App\Support\Translation\DatabaseOverrideLoader;
use Illuminate\Support\ServiceProvider;

class TranslationOverrideServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap the file loader for the override-aware one before the
        // (deferred) translator resolves it.
        $this->app->extend(
            'translation.loader',
            fn ($loader, $app) => new DatabaseOverrideLoader($app['files'], $app['path.lang']),
        );
    }
}
