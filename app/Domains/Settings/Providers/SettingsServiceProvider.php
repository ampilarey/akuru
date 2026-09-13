<?php

namespace App\Domains\Settings\Providers;

use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use App\Domains\Settings\Services\DatabaseSettingsRepository;
use Illuminate\Support\ServiceProvider;

class SettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // SPEC §42: "Bind key services to interfaces in the Laravel service
        // container so implementations are swappable." Settings was one of the
        // two required interfaces with no implementation, and its absence had
        // a cost — ten call sites in four other domains read
        // `DB::table('settings')` directly, so five domains knew the table's
        // name and shape and none could survive that shape changing.
        //
        // A singleton because a request reads settings repeatedly and they do
        // not change underneath it.
        $this->app->singleton(SettingsRepositoryInterface::class, DatabaseSettingsRepository::class);
    }

    public function boot(): void
    {
        //
    }
}
