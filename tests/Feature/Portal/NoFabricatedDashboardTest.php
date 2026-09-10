<?php

use Illuminate\Support\Facades\Route;

/**
 * The enhanced dashboard is gone, and should stay gone.
 *
 * It was a third dashboard beside `portal.home` (families) and
 * `portal.overview` (staff), reachable only by typing its URL — nothing in any
 * nav linked to it. Roughly thirty of its methods returned bare literals, and
 * several of those literals were **invented figures**: `'$5,250'` monthly
 * revenue, `'+12%'` growth, `'2.5 GB / 10 GB'` storage, `'99.9%'` uptime, and
 * an unconditional `'healthy'` database status.
 *
 * Every real number it computed already exists in AnalyticsService, so removing
 * it lost nothing. This test pins the removal: a dashboard that makes up
 * financial figures should not come back by accident.
 */
it('does not serve the removed enhanced dashboard', function () {
    expect(Route::has('enhanced.dashboard'))->toBeFalse();

    $this->withoutLocalizationMiddleware()
        ->get('/enhanced-dashboard')
        ->assertNotFound();
});

it('keeps no controller or view for it', function () {
    expect(file_exists(app_path('Domains/Portal/Http/Controllers/EnhancedDashboardController.php')))
        ->toBeFalse()
        ->and(file_exists(resource_path('views/dashboard/enhanced.blade.php')))
        ->toBeFalse();
});
