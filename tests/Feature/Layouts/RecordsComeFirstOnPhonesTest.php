<?php

/**
 * docs/MOBILE_FIRST.md rule 4 (MF3, STATUS §5sx): a page that lists the
 * person's records and lets them add one folds the form under one *New …*
 * button on a phone (`PhoneFold`), so the records start on the first screen;
 * the form opens again when it comes back with errors. `phone-first.mjs`
 * walks each page signed in at 390 px and holds the records — marked
 * `data-phone-main` — on the first screen.
 */
dataset('record pages', [
    'absence notes' => ['Portal/AbsenceNotes', 'absence_new', 'portal'],
    'events' => ['Portal/Events', 'events_new', 'portal'],
    'requests' => ['Academics/Requests/Index', 'requests_new', 'academics'],
    'plans' => ['Academics/Plans/Index', 'plans_new', 'academics'],
    'materials' => ['Academics/Materials/Index', 'materials_new', 'academics'],
    'behaviour' => ['Academics/Behavior/Index', 'behavior_new', 'academics'],
]);

it('folds the new form on a phone and marks the records', function (string $page, string $phrase, string $group) {
    $source = (string) file_get_contents(resource_path("js/Pages/{$page}.jsx"));

    expect($source)->toContain('import PhoneFold from')
        ->and($source)->toMatch('/<PhoneFold label=\{t\.'.$phrase.' \|\| [^}]+\} forceOpen=\{Object\.keys\(\w+\.errors\)\.length > 0\}/')
        ->and($source)->toContain('data-phone-main')
        ->and(strpos($source, '</PhoneFold>'))->toBeLessThan(strpos($source, 'data-phone-main'));

    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(__("{$group}.{$phrase}", [], $locale))->not->toBe("{$group}.{$phrase}");
    }
})->with('record pages');

it('shows the fold\'s button on a phone only, and opens the form on a refusal', function () {
    $fold = (string) file_get_contents(resource_path('js/Components/PhoneFold.jsx'));
    expect($fold)->toContain("'(min-width: 768px)'")
        ->toContain('if (forceOpen)')
        ->toContain('className="filter-fold-toggle"');
    expect((string) file_get_contents(resource_path('css/app.css')))
        ->toMatch('/@media \(min-width: 768px\) \{\s*\.filter-fold > summary \{\s*display: none;/');
});
