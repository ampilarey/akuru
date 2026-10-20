<?php

/**
 * docs/MOBILE_FIRST.md rule 4 (MF3, MF7; STATUS §5sx, §5tb): a page that lists
 * records and lets you add one puts the records first on a phone — its
 * posting form folds under one button (`PhoneFold`). A page whose posting
 * form comes before its list without one is either folded or listed in
 * Baselines/forms_before_lists.php with the reason it is not (the form is
 * the page, a single button, an editor).
 *
 * The reading is a heuristic over the page's JSX: the first `<form` whose
 * opening posts or puts, before the first `<table`, `<ul` or `.map((`.
 *
 * Filesystem only.
 */
function formsBeforeLists(): array
{
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js/Pages'), FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.jsx')) {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        if (str_contains($source, 'PhoneFold') || ($page = strpos($source, 'export default function')) === false) {
            continue;
        }
        if (! preg_match('/return \(\s*<AppShell/', $source, $shell, PREG_OFFSET_CAPTURE, $page)) {
            continue;
        }
        $jsx = substr($source, $shell[0][1]);
        $form = null;
        preg_match_all('/<form\b/', $jsx, $forms, PREG_OFFSET_CAPTURE);
        foreach ($forms[0] as [, $at]) {
            if (preg_match('/\.(post|put)\(/', substr($jsx, $at, 600))) {
                $form = $at;
                break;
            }
        }
        if ($form !== null && preg_match('/<table\b|<ul\b|\.map\(\(/', $jsx, $list, PREG_OFFSET_CAPTURE) && $form < $list[0][1]) {
            $found[] = str_replace(base_path().'/', '', $file->getPathname());
        }
    }
    sort($found);

    return $found;
}

it('puts the records before the new form on a phone, or says why not', function () {
    $baseline = require __DIR__.'/Baselines/forms_before_lists.php';
    $current = formsBeforeLists();

    expect(array_values(array_diff($current, array_keys($baseline))))->toBeEmpty(
        "Pages whose new form comes before their list on a phone:\n  ".implode("\n  ", array_diff($current, array_keys($baseline)))
        ."\n\nWrap the form in <PhoneFold label={…} forceOpen={Object.keys(form.errors).length > 0}> "
        .'(resources/js/Components/PhoneFold.jsx), or, if the form is the page, add it to '
        .'tests/Architecture/Baselines/forms_before_lists.php with the reason. docs/MOBILE_FIRST.md rule 4.'
    );
    expect(array_values(array_diff(array_keys($baseline), $current)))->toBeEmpty(
        "No longer a form before a list — delete these from Baselines/forms_before_lists.php:\n  ".implode("\n  ", array_diff(array_keys($baseline), $current))
    );
    expect(array_filter($baseline, fn ($reason) => trim((string) $reason) === ''))->toBeEmpty();
});

it('gives the fold a shared "Add new" in three languages when a page has no opener of its own', function () {
    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(__('nav.add_new', [], $locale))->not->toBe('nav.add_new');
    }
    expect((string) file_get_contents(resource_path('js/Components/PhoneFold.jsx')))->toContain('shell.add_new');
    expect((string) file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php')))->toContain("'add_new'");
});
