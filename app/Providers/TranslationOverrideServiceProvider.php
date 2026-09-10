<?php

namespace App\Providers;

use App\Support\Contracts\MachineTranslatorInterface;
use App\Support\Translation\DatabaseOverrideLoader;
use App\Support\Translation\NullMachineTranslator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Translation\FileLoader;

class TranslationOverrideServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Swap the file loader for the override-aware one before the
        // (deferred) translator resolves it.
        //
        // Take the paths from the loader being replaced rather than naming
        // them. Laravel registers **two**:
        //
        //     new FileLoader($app['files'], [__DIR__.'/lang', $app['path.lang']])
        //
        // — the framework's own lang directory *and* the app's. This provider
        // used to pass `$app['path.lang']` alone, which silently dropped the
        // framework half, and this app has no `lang/en/validation.php` of its
        // own. Every message Laravel ships then resolved to its raw key: a
        // failed login read "auth.failed", and every validation error in the
        // application read "validation.required" and the like.
        //
        // Inheriting `paths()` also means a future Laravel that registers a
        // third path keeps working without anyone noticing this file.
        $this->app->extend('translation.loader', function ($loader, $app) {
            $paths = $loader instanceof FileLoader ? $loader->paths() : [$app['path.lang']];

            $replacement = new DatabaseOverrideLoader($app['files'], $paths);

            if ($loader instanceof FileLoader) {
                foreach ($loader->jsonPaths() as $jsonPath) {
                    $replacement->addJsonPath($jsonPath);
                }
                foreach ($loader->namespaces() as $namespace => $hint) {
                    $replacement->addNamespace($namespace, $hint);
                }
            }

            return $replacement;
        });

        // T2: suggestion-only machine translation. Null by default; a
        // provider slice can add real drivers to this match.
        $this->app->bind(MachineTranslatorInterface::class, function () {
            return match (config('services.machine_translator.driver', 'null')) {
                default => new NullMachineTranslator,
            };
        });
    }
}
