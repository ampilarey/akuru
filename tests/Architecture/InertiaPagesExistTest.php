<?php

/**
 * Every `Inertia::render('X')` target has a page component on disk.
 *
 * **This one is prevention, not a fix — there are no violations today.** All
 * 156 render calls resolve. It is here because the failure it catches belongs
 * to a family this codebase has already been bitten by twice in one day, and
 * every member of that family looks identical from the outside: **HTTP 200, and
 * a page the user cannot use.**
 *
 *   - Five routes pointed at controller methods that did not exist. Those at
 *     least 500'd, and `RoutesHaveControllerMethodsTest` now catches them.
 *   - Eight submit buttons chained `form.transform(...).post(...)`, which throws
 *     in Inertia v3. No POST, no error anybody could see, and the screen guards
 *     passed because the page rendered perfectly.
 *
 * A renamed or mistyped page component is the third. The controller returns
 * 200, Inertia fails to resolve the component in the browser, and the visitor
 * gets a blank screen. Every screen guard in this suite would pass: they assert
 * on the HTTP status, and the status is fine.
 *
 * The check costs nothing — no database, no HTTP, no fixture, just the
 * filesystem — which is the argument for having it before the first violation
 * rather than after.
 */
it('renders only Inertia pages that exist', function () {
    $pagesRoot = base_path('resources/js/Pages');

    $available = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pagesRoot)) as $file) {
        if (! $file->isFile() || ! in_array($file->getExtension(), ['jsx', 'tsx', 'vue'], true)) {
            continue;
        }

        $name = str_replace($pagesRoot.'/', '', $file->getPathname());
        $available[preg_replace('/\.(jsx|tsx|vue)$/', '', $name)] = true;
    }

    // If this collapses, the page directory moved and the test is comparing
    // against nothing.
    expect(count($available))->toBeGreaterThan(100);

    $missing = [];
    $checked = 0;

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        // Only literal page names can be checked. A variable page name is rare
        // here and would need its own approach; none exists today.
        if (! preg_match_all('/Inertia::render\(\s*[\'"]([^\'"]+)[\'"]/', $source, $matches)) {
            continue;
        }

        foreach ($matches[1] as $page) {
            $checked++;

            if (! isset($available[$page])) {
                $missing[] = sprintf(
                    '%s → %s',
                    str_replace(base_path().'/', '', $file->getPathname()),
                    $page
                );
            }
        }
    }

    expect($checked)->toBeGreaterThan(100);

    $missing = array_values(array_unique($missing));

    expect($missing)->toBeEmpty(
        count($missing)." Inertia::render() call(s) name a page component that does not exist.\n"
        ."The controller still answers 200 and the visitor gets a blank screen,\n"
        ."so no screen guard will catch this:\n  "
        .implode("\n  ", $missing)
        ."\nCreate the component under resources/js/Pages, or correct the name.\n"
    );
});
