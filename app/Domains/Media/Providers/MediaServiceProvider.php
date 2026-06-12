<?php

namespace App\Domains\Media\Providers;

use App\Domains\Media\Contracts\ImageProcessorInterface;
use App\Domains\Media\Contracts\MediaStorageInterface;
use App\Domains\Media\Services\LaravelMediaStorage;
use App\Domains\Media\Services\WebPImageService;
use Illuminate\Support\ServiceProvider;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImageProcessorInterface::class, WebPImageService::class);
        $this->app->singleton(MediaStorageInterface::class, LaravelMediaStorage::class);
    }

    public function boot(): void
    {
        //
    }
}
