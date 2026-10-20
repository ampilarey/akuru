<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The old Blade screens still running, in Dhivehi and Arabic (BACKLOG C21's
 * last item; the owner, 2026-10-20: "do all" — translated, not retired).
 * OB1: the analytics dashboard and reports, and the old layout's menu.
 *
 * Reading them found the reports page broken: *Generate report* posted a
 * form and was answered with raw JSON, and the page printed no flash.
 */
uses(RefreshDatabase::class);

function ob1Views(): array
{
    return [
        'analytics/dashboard.blade.php',
        'analytics/reports.blade.php',
        'layouts/app.blade.php',
        'layouts/guest.blade.php',
        'layouts/navigation.blade.php',
    ];
}

function obPrintsNoEnglish(array $views): array
{
    $found = [];
    foreach ($views as $view) {
        $path = resource_path('views/'.$view);
        // The app's name, where a layout's title falls back to it.
        foreach ([...bladeBareEnglish($path), ...bladeEnglishLiterals($path, ['Akuru LMS', 'Akuru Institute'])] as $text) {
            $found[] = "{$view}: {$text}";
        }
    }

    return $found;
}

function obKeysIn(array $views, string $books): array
{
    $sources = implode("\n", array_map(fn ($v) => (string) file_get_contents(resource_path('views/'.$v)), $views));
    preg_match_all("/__\\('((?:{$books})\\.[A-Za-z_]+)/", $sources, $keys);

    // A key built at runtime (`'analytics.health_'.$status`) is checked by its own test.
    return array_values(array_filter(array_unique($keys[1]), fn ($key) => ! str_ends_with($key, '_')));
}

it('prints no English of the analytics pages\' or the old menu\'s own', function () {
    expect(obPrintsNoEnglish(ob1Views()))->toBe([]);

    foreach (obKeysIn(ob1Views(), 'analytics|nav|common|site') as $key) {
        foreach (['en', 'dv', 'ar'] as $locale) {
            expect(__($key, [], $locale))->not->toBe($key, "{$key} in {$locale}");
        }
    }
    foreach (['healthy', 'warning', 'critical'] as $status) {
        expect(__('analytics.health_'.$status, [], 'dv'))->not->toBe('analytics.health_'.$status);
    }
    foreach (['completed', 'pending', 'failed', 'running'] as $status) {
        expect(__('analytics.status_'.$status, [], 'ar'))->not->toBe('analytics.status_'.$status);
    }
    // Every report type and category the form offers is named.
    foreach ([...array_keys(\App\Domains\Settings\Models\Report::getAvailableTypes())] as $type) {
        expect(__('analytics.type_'.$type, [], 'dv'))->not->toBe('analytics.type_'.$type);
    }
    foreach (array_keys(\App\Domains\Settings\Models\Report::getAvailableCategories()) as $category) {
        expect(__('analytics.category_'.$category, [], 'ar'))->not->toBe('analytics.category_'.$category);
    }
});

it('shows the dashboard and the reports in Dhivehi, and generating a report comes back to the list', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    app()->setLocale('dv');
    $web = test()->withoutLocalizationMiddleware()->actingAs($admin);

    $web->get('/analytics')->assertOk()->assertSee(__('analytics.title', [], 'dv'))->assertSee(__('analytics.system_health', [], 'dv'))->assertDontSee('Analytics Dashboard');
    $web->get('/analytics/reports')->assertOk()->assertSee(__('analytics.generate_new', [], 'dv'))->assertSee(__('analytics.none', [], 'dv'));

    $web->post('/analytics/reports/generate', ['name' => 'Walk', 'type' => 'user_analytics', 'category' => 'analytics', 'format' => 'json'])
        ->assertRedirect(route('analytics.reports'))->assertSessionHas('success', __('analytics.generated', [], 'dv'));
    $web->get('/analytics/reports')->assertSee(__('analytics.type_user_analytics', [], 'dv'))->assertSee(__('analytics.status_completed', [], 'dv'))->assertSee('data-testid="flash-success"', false);
});
