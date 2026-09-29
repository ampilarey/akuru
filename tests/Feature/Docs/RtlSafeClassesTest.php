<?php

/**
 * CLAUDE.md: "All screens trilingual-ready (EN/DV/AR) and **RTL-safe**."
 *
 * Tailwind's physical utilities do not mirror. `text-left` stays left in
 * Dhivehi and Arabic, `ml-2` puts the gap on the wrong side, and the page looks
 * subtly broken to two of this school's three languages. The logical
 * equivalents — `text-start`, `text-end`, `ms-`, `me-`, `ps-`, `pe-` — flip with
 * the document direction.
 *
 * The codebase was half converted: 50 `text-start` against 80 `text-left`, so
 * the intent was there and the rule was quietly not being kept. **I added
 * several of the offenders myself** while building this session's screens,
 * which is precisely why this is a test and not a one-off tidy-up.
 */
it('uses no physical directional classes in any screen', function () {
    $offenders = [];

    $files = array_merge(
        glob(resource_path('js/Pages/**/*.jsx'), GLOB_BRACE) ?: [],
        iterator_to_array(
            new RegexIterator(
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS)
                ),
                '/\.jsx$/'
            )
        ),
    );

    foreach (array_unique(array_map('strval', $files)) as $file) {
        $source = file_get_contents($file);

        // Whole class tokens only, so `mr-2` matches but `--mr-2` or a word
        // ending in "pl-3" does not.
        if (preg_match_all('/(?<![\w-])(text-(?:left|right)|(?:ml|mr|pl|pr)-[0-9.]+)(?![\w-])/', $source, $matches)) {
            $offenders[str_replace(resource_path('js').'/', '', $file)] = array_unique($matches[1]);
        }
    }

    expect($offenders)->toBe(
        [],
        'Use the logical equivalents so the layout mirrors in Dhivehi and Arabic: '
        .'text-left→text-start, text-right→text-end, ml-→ms-, mr-→me-, pl-→ps-, pr-→pe-.'
    );
});

/**
 * STATUS §5lp: the public site now follows the language's direction too, so
 * its Blade views keep the same rule — every view the public layout renders,
 * the layout itself, and the public components.
 */
it('uses no physical directional classes on the public site', function () {
    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS));
    $offenders = [];

    foreach ($views as $file) {
        $path = (string) $file;
        if (! str_ends_with($path, '.blade.php')) {
            continue;
        }
        $source = file_get_contents($path);
        $public = str_contains($path, '/views/public/') || str_contains($path, '/views/components/public/')
            || str_contains($source, 'layouts.public');
        if (! $public) {
            continue;
        }
        // Only inside class attributes: a one-sided position or gap there is what fails to mirror.
        preg_match_all('/class=(["\'])(.*?)\1/s', $source, $attributes);
        foreach ($attributes[2] as $classes) {
            if (preg_match_all('/(?<![\w-])((?:[a-z]+:)*(?:text-(?:left|right)|(?:ml|mr|pl|pr)-[0-9.]+|border-[lr]-[0-9]+|rounded-[lr]-[a-z]+))(?![\w-])/', $classes, $matches)) {
                $offenders[str_replace(resource_path('views').'/', '', $path)] = array_values(array_unique(array_merge($offenders[str_replace(resource_path('views').'/', '', $path)] ?? [], $matches[1])));
            }
        }
    }

    expect($offenders)->toBe([], 'Use the logical equivalents (text-start, ms-, pe-, border-s-, rounded-e-) so the public site mirrors in Dhivehi and Arabic.');
});
