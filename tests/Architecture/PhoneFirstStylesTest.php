<?php

/**
 * CLAUDE.md "Phone-first" (docs/MOBILE_FIRST.md, STATUS §5sv). Most people
 * reach Akuru on a phone, so a screen is designed at 390px first and widened
 * with `sm:`/`md:`/`lg:` — never designed for a desk and patched for phones.
 *
 * Two things a test can see without a browser, each pinned so it can only
 * get better:
 *
 * 1. **Desktop-first CSS.** A `@media (max-width: …)` rule is a desk layout
 *    corrected for phones afterwards, and Tailwind's `max-sm:` … `max-2xl:`
 *    variants are the same thing inline. No new file may have one, and no
 *    file may have more than its baseline count.
 * 2. **Tables that stay tables on a phone.** Every `<table>` in an Inertia
 *    page must be `table-stack` (app.css: below `sm` each row becomes a card
 *    of label–value lines), or carry `data-phone-scroll` when it is a grid
 *    that cards would scatter (a timetable), scrolling in its own box. The
 *    pages with a table that is neither are listed; the list may only shrink.
 *
 * What only a browser can see — that the thing a page is for is on the first
 * phone screen, that nothing scrolls sideways — is `phone-first.mjs`.
 *
 * Filesystem only: no database, no HTTP.
 */
function phoneFirstFiles(array $roots, array $suffixes): array
{
    $found = [];
    foreach ($roots as $root) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($root), FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            foreach ($suffixes as $suffix) {
                if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                    $found[] = str_replace(base_path().'/', '', $file->getPathname());
                }
            }
        }
    }
    sort($found);

    return $found;
}

it('adds no desktop-first CSS: no new max-width media query, no max-* variant', function () {
    $baseline = require __DIR__.'/Baselines/desktop_first_media_queries.php';
    $grown = [];
    $variants = [];

    foreach (phoneFirstFiles(['resources/css', 'resources/views', 'resources/js'], ['.css', '.blade.php', '.jsx', '.js']) as $path) {
        $source = (string) file_get_contents(base_path($path));
        $count = preg_match_all('/@media[^{]*max-width/i', $source);
        if ($count > ($baseline[$path] ?? 0)) {
            $grown[] = $path.' ('.$count.', baseline '.($baseline[$path] ?? 0).')';
        }
        if (preg_match_all('/\bmax-(sm|md|lg|xl|2xl):/', $source) > 0) {
            $variants[] = $path;
        }
    }

    expect($grown)->toBeEmpty(
        "Desktop-first media queries added:\n  ".implode("\n  ", $grown)
        ."\n\nWrite the phone layout as the default and the wider one under `@media (min-width: …)` "
        .'(or Tailwind `sm:`/`md:`/`lg:`). docs/MOBILE_FIRST.md. If you removed one, lower the count in '
        .'tests/Architecture/Baselines/desktop_first_media_queries.php.'
    );
    expect($variants)->toBeEmpty("Tailwind max-* variants (desktop-first) in:\n  ".implode("\n  ", $variants));
});

it('stacks every new table into cards on a phone, and the old ones only get fewer', function () {
    $baseline = require __DIR__.'/Baselines/tables_not_stacked_on_phones.php';
    $current = array_values(array_filter(
        // AD1: the shared components too — the ID-card queue's table, drawn
        // on two office pages, was a desk table on a phone.
        phoneFirstFiles(['resources/js/Pages', 'resources/js/Components'], ['.jsx']),
        // Per table (MF4): every <table> is `table-stack`, or says it must stay a
        // grid that scrolls in its own box (`data-phone-scroll`, a timetable).
        fn (string $path) => (bool) array_filter(
            preg_match_all('/<table\b[^>]*>/', (string) file_get_contents(base_path($path)), $tags) ? $tags[0] : [],
            fn (string $tag) => ! str_contains($tag, 'table-stack') && ! str_contains($tag, 'data-phone-scroll'),
        ),
    ));

    expect(array_values(array_diff($current, $baseline)))->toBeEmpty(
        "Pages with a table that stays a table on a phone:\n  ".implode("\n  ", array_diff($current, $baseline))
        ."\n\nGive the <table> `table-stack` and each <td> a `data-label` (app.css), so a phone reads each "
        .'row as a card. docs/MOBILE_FIRST.md.'
    );
    expect(array_values(array_diff($baseline, $current)))->toBeEmpty(
        "These pages are stacked now — delete their lines from tests/Architecture/Baselines/tables_not_stacked_on_phones.php:\n  "
        .implode("\n  ", array_diff($baseline, $current))
    );
});
