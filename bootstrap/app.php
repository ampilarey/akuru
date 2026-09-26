<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'payments/bml/callback',
            'webhooks/bml',
        ]);
        // BOOKSHOP_PLAN B9f: the shop subdomain and shops' own domains go to
        // the canonical site before anything else runs.
        $middleware->prepend(\App\Domains\Bookshop\Http\Middleware\RedirectShopHosts::class);
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\ConvertEnroll403ToRedirect::class,
            // BOOKSHOP_PLAN B11: the office can close the public shop; the notice needs the session (a signed-in customer's links).
            \App\Domains\Bookshop\Http\Middleware\EnsureBookstoreOpen::class,
            // SPEC §52.27: the Qur'an/Hifz module is feature-flagged, and
            // §52.29 requires the platform to work with it disabled. Applied
            // to the whole web group because the module's 57 routes are
            // declared inline throughout the routes file rather than in one
            // group; the middleware decides by controller namespace, which
            // cannot drift the way a route-name list would.
            \App\Http\Middleware\EnsureQuranModuleEnabled::class,
        ]);

        // Register Laravel Localization middleware aliases
        $middleware->alias([
            'localeSessionRedirect' => \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            'localizationRedirect' => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            'localeViewPath' => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,
            'trackActivity' => \App\Http\Middleware\TrackUserActivity::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'verified_contact' => \App\Http\Middleware\EnsureVerifiedContact::class,
            'convert_enroll_403' => \App\Http\Middleware\ConvertEnroll403ToRedirect::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 401/403 enrollment redirects are handled by ConvertEnroll403ToRedirect middleware.
    })->create();
