<?php

namespace App\Domains\Website\Providers;

use App\Domains\Website\Actions\ComposeHreflangLinksAction;
use App\Domains\Website\Actions\ComposeOrganizationJsonLdAction;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class WebsiteServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('public.layouts.public', function ($view): void {
            $view->with('organizationJsonLd', app(ComposeOrganizationJsonLdAction::class)->execute());
            $view->with('hreflangLinks', app(ComposeHreflangLinksAction::class)->execute());
        });
    }
}
