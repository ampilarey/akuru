<?php

namespace App\Providers;

use App\Domains\Settings\Models\Setting;
use App\Support\Contracts\DocumentRendererInterface;
use App\Support\Contracts\PdfConverterInterface;
use App\Support\Services\ChromePdfConverter;
use App\Support\Services\HtmlDocumentRenderer;
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
        $this->app->singleton(DocumentRendererInterface::class, HtmlDocumentRenderer::class);
        // ADR-012 (amended, STATUS §5lr): PDF only where a Chrome is configured; disabled elsewhere.
        $this->app->singleton(PdfConverterInterface::class, fn () => new ChromePdfConverter(
            config('documents.pdf.chrome_path'),
            (int) config('documents.pdf.timeout', 60),
        ));
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
