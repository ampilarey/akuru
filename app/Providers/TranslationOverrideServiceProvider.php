<?php

namespace App\Providers;

use App\Support\Contracts\MachineTranslatorInterface;
use App\Support\Translation\DatabaseOverrideLoader;
use App\Support\Translation\NullMachineTranslator;
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

        // T2: suggestion-only machine translation. Null by default; a
        // provider slice can add real drivers to this match.
        $this->app->bind(MachineTranslatorInterface::class, function () {
            return match (config('services.machine_translator.driver', 'null')) {
                default => new NullMachineTranslator,
            };
        });
    }
}
