<?php

use App\Domains\Settings\Actions\ClearApplicationCachesAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Admin-panel audit finding 13 (STATUS §5il): *Clear all caches* ran
 * `config:clear`, so a production host deployed with `config:cache` ran
 * uncached from that click until the next deploy. The configuration is now
 * rebuilt where it was cached and cleared where it was not.
 *
 * The facade is mocked because a real `config:cache` writes
 * `bootstrap/cache/config.php` and every later test would read it.
 */
it('rebuilds a cached configuration rather than dropping it', function () {
    Artisan::shouldReceive('call')->once()->with('cache:clear');
    Artisan::shouldReceive('call')->once()->with('view:clear');
    Artisan::shouldReceive('call')->once()->with('route:clear');
    Artisan::shouldReceive('call')->once()->with('config:cache');
    Artisan::shouldReceive('call')->never()->with('config:clear');

    $ran = app(ClearApplicationCachesAction::class)->execute(configurationWasCached: true);

    expect($ran)->toBe(['cache:clear', 'view:clear', 'route:clear', 'config:cache']);
});

it('clears an uncached configuration, and never caches routes', function () {
    Artisan::shouldReceive('call')->once()->with('cache:clear');
    Artisan::shouldReceive('call')->once()->with('view:clear');
    Artisan::shouldReceive('call')->once()->with('route:clear');
    Artisan::shouldReceive('call')->once()->with('config:clear');
    Artisan::shouldReceive('call')->never()->with('config:cache');
    Artisan::shouldReceive('call')->never()->with('route:cache');

    $ran = app(ClearApplicationCachesAction::class)->execute(configurationWasCached: false);

    expect($ran)->not->toContain('config:cache', 'route:cache');
});

it('runs from the settings screen for the system admin alone', function () {
    Artisan::shouldReceive('call')->times(4);

    $this->withoutLocalizationMiddleware()->actingAs(actingSystemAdmin())
        ->from(route('admin.settings.index'))
        ->post(route('admin.settings.clear-cache'))
        ->assertRedirect(route('admin.settings.index'))
        ->assertSessionHas('success');

    // The educational admin has no business here (ADR-040 slice 2).
    $this->withoutLocalizationMiddleware()->actingAs(actingPeopleAdmin())
        ->post(route('admin.settings.clear-cache'))->assertForbidden();
});
