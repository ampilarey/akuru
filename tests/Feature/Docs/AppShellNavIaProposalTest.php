<?php

it('records the AppShell nav IA proposal before any shell redesign', function () {
    $path = base_path('docs/APPSHELL_NAV_IA.md');

    expect($path)->toBeFile();

    $text = file_get_contents($path);

    expect($text)->toContain('proposal only');
    expect($text)->toContain('do not implement until confirmed');
    expect($text)->toContain('by role');
    expect($text)->toContain('Frequency');
    expect($text)->toContain('Today');
    expect($text)->toContain('Years');
    expect($text)->toContain('Exams');
    expect($text)->toContain('Decision required');
    expect($text)->toContain('AppShell.jsx');
    expect($text)->toContain('74');
    expect($text)->not->toContain('implemented in this PR');
});

it('does not change AppShell.jsx as part of the IA proposal', function () {
    $shell = file_get_contents(base_path('resources/js/Layouts/AppShell.jsx'));

    expect($shell)->toContain('href="/academics/registers/today"');
    expect($shell)->toContain('href="/academics/years"');
    expect($shell)->toContain('href="/exams/schedule"');

    $linkCount = substr_count($shell, '<Link href=');
    expect($linkCount)->toBe(77);
});

it('does not register a product route for the proposal document', function () {
    expect(file_exists(base_path('docs/APPSHELL_NAV_IA.md')))->toBeTrue();
    expect(\Illuminate\Support\Facades\Route::has('docs.appshell-nav-ia'))->toBeFalse();
});
