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

    // 83 → 86: +Ops checklist, +Feature walkthrough, +Translations
    // (permission-gated admin links; conscious bump per this guard's intent).
    // 86 → 87: +Materials (E13a). The guard exists to stop a nav *redesign*
    // arriving unannounced, not to freeze the list — one link for a new screen
    // that would otherwise be unreachable is the bump it is meant to allow.
    // 87 → 88: +My day (E1b), the teacher's home.
    // 88 → 89: +Absences (E10b), the office's morning list.
    // 105 → 106: +Reports (§33), the admin reports hub. Six of §33's ten
    //   reports were computed and scattered across three screens and three had
    //   no reader at all, so the page is exactly the "otherwise unreachable"
    //   case this allowance exists for. It also makes the nav one link worse,
    //   which is KNOWN_ISSUES P3 #11 and still the owner's call.
    // 106 → 107: +My meetings (`/teach/meetings`). The screen a teacher's own
    //   parent-teacher bookings appear on, which had no screen at all — the
    //   office's `/academics/meetings` answers 403 to a teacher, and neither
    //   `/teach/schedule` nor `/portal/teacher` mentions meetings
    //   (KNOWN_ISSUES #30). Unreachable without this link, which is the case
    //   the allowance above is for. It also makes the nav one link worse,
    //   which remains P3 #11 and the owner's call.
    $linkCount = substr_count($shell, '<Link href=');
    expect($linkCount)->toBe(107);
});

it('does not register a product route for the proposal document', function () {
    expect(file_exists(base_path('docs/APPSHELL_NAV_IA.md')))->toBeTrue();
    expect(\Illuminate\Support\Facades\Route::has('docs.appshell-nav-ia'))->toBeFalse();
});
