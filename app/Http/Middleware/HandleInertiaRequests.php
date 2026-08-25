<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            ...parent::share($request),
            'locale' => $locale,
            'locales' => ['en', 'dv', 'ar'],
            'locale_urls' => collect(['en', 'dv', 'ar'])->mapWithKeys(
                fn (string $code) => [$code => LaravelLocalization::getLocalizedURL($code) ?: '/'.$code],
            )->all(),
            'rtl' => in_array($locale, ['ar', 'dv'], true),
            'auth' => [
                'user' => $request->user()?->only(['id', 'name', 'email']),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'i18n' => [
                'learn' => trans('learn'),
            ],
        ];
    }
}
