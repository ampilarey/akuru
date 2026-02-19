<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\ConvertEnroll403ToRedirect::class,
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
        $enrollMessage = 'Your session may have expired or this contact is already registered. Please go back to the course and start again, or use a different mobile/email.';

        $isEnrollRequest = function (Request $request): bool {
            $name = $request->route()?->getName();
            $path = $request->path();
            return $name === 'courses.register.enroll'
                || str_contains($path, 'courses/register/enroll')
                || ($request->isMethod('POST') && str_contains($path, 'register') && str_contains($path, 'enroll'));
        };

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) use ($enrollMessage, $isEnrollRequest) {
            if ($isEnrollRequest($request)) {
                return redirect()->back()->withErrors(['_authorization' => $enrollMessage])->withInput();
            }
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) use ($enrollMessage, $isEnrollRequest) {
            if ($isEnrollRequest($request)) {
                return redirect()->back()->withErrors(['_authorization' => $enrollMessage])->withInput();
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) use ($enrollMessage, $isEnrollRequest) {
            if ($isEnrollRequest($request)) {
                return redirect()->back()->withErrors(['_authorization' => $enrollMessage])->withInput();
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) use ($enrollMessage, $isEnrollRequest) {
            if (in_array($e->getStatusCode(), [401, 403], true) && $isEnrollRequest($request)) {
                return redirect()->back()->withErrors(['_authorization' => $enrollMessage])->withInput();
            }
        });

        $exceptions->respond(function ($response, \Throwable $e, Request $request) use ($enrollMessage, $isEnrollRequest) {
            if (in_array($response->getStatusCode(), [401, 403], true)
                && ($isEnrollRequest($request) || ($request->isMethod('POST') && str_contains($request->path() . $request->header('Referer', ''), 'enroll')))) {
                return redirect()->back()->withErrors(['_authorization' => $enrollMessage])->withInput();
            }
            return $response;
        });
    })->create();
