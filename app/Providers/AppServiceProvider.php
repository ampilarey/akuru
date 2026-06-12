<?php

namespace App\Providers;

use App\Domains\Settings\Models\Setting;
use App\Support\Contracts\DocumentRendererInterface;
use App\Support\Services\StubDocumentRenderer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DocumentRendererInterface::class, StubDocumentRenderer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share site settings globally with all views (cached for 10 min)
        View::composer('*', function ($view) {
            if (Schema::hasTable('settings')) {
                $siteSettings = Cache::remember('site_settings', 600, fn () => Setting::allKeyed());
                $view->with('siteSettings', $siteSettings);
            }
        });
    }
}
