<?php

/**
 * ROADMAP §5 "Frontend Rules", first line, and CLAUDE.md's Conventions, which
 * say it again:
 *
 *   > **All new UIs**: Inertia + React. No new Blade screens.
 *   > Existing Blade pages keep running until their replacement ships; public
 *   > Website domain may stay Blade until its redesign.
 *
 * **The rule has held on its own.** No Blade view has been added since the tree
 * was imported, across every slice since. This pins that rather than fixing
 * anything — which is the point: the cost of a rule nobody checks is paid late,
 * by the one screen that quietly arrives in the wrong stack.
 *
 * What that screen would miss is concrete, not stylistic. A Blade page gets no
 * `AppShell`, so it has none of the navigation, no `auth.can` permissions
 * summary, no `rtl` flag, and none of the Inertia shared props — the ones
 * `InertiaSharedPropsTest` pins because every page depends on them. Dhivehi and
 * Arabic readers are the ones who notice first.
 *
 * **The list may only shrink, and that direction is the interesting one.**
 * ROADMAP says existing Blade keeps running "until their replacement ships", so
 * deleting an entry is a migration landing. Nothing was counting those; this
 * count is the metric §5 implies. When you retire a screen, delete its line and
 * correct the count in the baseline's header.
 *
 * Filesystem only: no database, no HTTP, no fixture.
 */
it('adds no new Blade screen, and counts the ones still to be replaced', function () {
    $baseline = require __DIR__.'/Baselines/blade_screens.php';

    $current = [];
    $root = base_path('resources/views');

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $current[] = str_replace($root.'/', '', $file->getPathname());
        }
    }

    sort($current);
    sort($baseline);

    $added = array_values(array_diff($current, $baseline));

    expect($added)->toBeEmpty(
        "New Blade screens:\n  "
        .implode("\n  ", $added)
        ."\n\nROADMAP §5: \"All new UIs: Inertia + React. No new Blade screens.\"\n\n"
        .'A Blade page gets no AppShell, so no navigation, no permissions summary, no rtl '
        .'flag and none of the Inertia shared props every other screen relies on. Build it '
        .'as an Inertia page instead — or, if this genuinely belongs in the public Blade '
        .'site, add it to tests/Architecture/Baselines/blade_screens.php with the reason.'
    );

    $removed = array_values(array_diff($baseline, $current));

    expect($removed)->toBeEmpty(
        "These Blade screens are gone — delete them from the baseline and correct its count:\n  "
        .implode("\n  ", $removed)
        ."\n\nThat is the expected direction: ROADMAP §5 keeps existing Blade running only "
        .'"until their replacement ships". The baseline may only shrink, and its count is '
        .'how much of the migration is left.'
    );
});
