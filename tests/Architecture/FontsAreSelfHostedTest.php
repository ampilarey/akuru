<?php

/**
 * The fonts are self-hosted (docs/ADMIN_PANEL.md §7 P6, STATUS §5np).
 *
 * `app.css` used to open with a Google Fonts `@import` that held every shell
 * page's first paint on a round trip to Google, and the Blade layouts fetched
 * Figtree from bunny.net. The faces now come from `@fontsource` packages that
 * Vite copies into the build. This test keeps a third-party font host out of
 * the stylesheet and the layouts every page renders through.
 *
 * The one place a font may still come from Google is a shop's own theme
 * (`public/shop/_theme.blade.php`): the vendor chooses the face
 * (BOOKSHOP_PLAN B4), and that is their page, not the shell's.
 */
it('loads no font from Google or bunny.net in the stylesheet or the layouts', function () {
    $files = [
        resource_path('css/app.css'),
        resource_path('views/app.blade.php'),
        resource_path('views/layouts/app.blade.php'),
        resource_path('views/layouts/guest.blade.php'),
        resource_path('views/layouts/navigation.blade.php'),
        resource_path('views/public/layouts/public.blade.php'),
        resource_path('views/components/public/nav.blade.php'),
        resource_path('views/partials/pwa.blade.php'),
    ];

    $offenders = [];
    foreach ($files as $file) {
        if (! is_file($file)) {
            continue;
        }
        $contents = (string) file_get_contents($file);
        foreach (['fonts.googleapis.com', 'fonts.gstatic.com', 'fonts.bunny.net'] as $host) {
            if (str_contains($contents, $host)) {
                $offenders[] = str_replace(base_path().'/', '', $file).' → '.$host;
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('imports the three faces the shells use from the self-hosted packages', function () {
    $css = (string) file_get_contents(resource_path('css/app.css'));

    // The packages themselves are checked by the build, not here: CI runs
    // the tests without `node_modules`.
    expect($css)->toContain("@import '@fontsource/figtree/400.css';")
        ->toContain("@import '@fontsource/amiri/400.css';")
        ->toContain("@import '@fontsource/cairo/400.css';")
        ->and(json_decode((string) file_get_contents(base_path('package.json')), true)['dependencies'])->toHaveKeys(['@fontsource/amiri', '@fontsource/cairo', '@fontsource/figtree']);
});
