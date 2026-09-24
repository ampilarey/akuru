<?php

/**
 * The AppShell navigation IA was a proposal from 2026-08-26 to 2026-09-24
 * (docs/APPSHELL_NAV_IA.md), guarded here so the shell could not be redesigned
 * unannounced. The owner accepted it on 2026-09-24; this now guards the other
 * direction — that the flat strip does not creep back.
 */
it('records the AppShell nav IA as accepted and implemented', function () {
    $text = file_get_contents(base_path('docs/APPSHELL_NAV_IA.md'));

    expect($text)->toContain('Accepted')
        ->and($text)->toContain('implemented')
        ->and($text)->toContain('by role')
        ->and($text)->toContain('Frequency')
        ->and($text)->toContain('Today')
        ->and($text)->toContain('Years')
        ->and($text)->toContain('Exams')
        ->and($text)->toContain('BuildNavigationAction');
});

it('renders the shell from the shared nav rather than a flat list of links', function () {
    $shell = file_get_contents(base_path('resources/js/Layouts/AppShell.jsx'));

    expect($shell)->toContain('nav.primary.map')
        ->and($shell)->toContain('nav.groups.map')
        ->and($shell)->toContain('aria-expanded')
        // The screens live in App\Support\Navigation\NavigationMap, not here.
        // Alerts and the account link are the only literal destinations left.
        ->and(substr_count($shell, '<Link href="'))->toBeLessThanOrEqual(3)
        ->and($shell)->not->toContain('href="/academics/years"')
        ->and($shell)->not->toContain('href="/hr/payroll"');
});

it('does not register a product route for the proposal document', function () {
    expect(file_exists(base_path('docs/APPSHELL_NAV_IA.md')))->toBeTrue();
    expect(\Illuminate\Support\Facades\Route::has('docs.appshell-nav-ia'))->toBeFalse();
});
