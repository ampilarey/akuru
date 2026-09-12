<?php

/**
 * Every `{!! … !!}` in a Blade view is declared.
 *
 * Blade escapes `{{ }}` and does not escape `{!! !!}`, so each of these is a
 * deliberate decision to render HTML as HTML. Some are fine — a QR code the
 * system generated, a certificate body an admin composed for a PDF. Others are
 * author-written content on the public site, and those are only safe if
 * something sanitised them on the way in.
 *
 * Nothing did. `PageController` validated `body` as `required|string` and the
 * public page rendered it raw, on a surface an anonymous visitor sees and a
 * `supervisor` can author.
 *
 * Every authored surface now sanitises on write. What this test does is keep
 * the list honest: a new `{!! !!}` cannot be added without somebody saying
 * which kind it is, and a surface cannot quietly stop being sanitised.
 *
 * Filesystem only. No database, no HTTP.
 */
it('declares every raw HTML render in a Blade view', function () {
    /**
     * Each entry says why the raw render is acceptable.
     *
     * `sanitised on write` — the stored value has been through
     * `HtmlSanitizer`, so every reader of it is safe rather than each render
     * site having to remember.
     *
     * `system-generated` — the application composed the markup itself; no user
     * input reaches it.
     *
     * `UNSANITISED` — a known gap, kept explicit so it is not mistaken for
     * something already handled. These are internal-facing surfaces; the
     * public-site ones were closed first because their audience is anonymous
     * and their authoring role is broader.
     */
    $declared = [
        'admin/public-site/pages/show.blade.php' => 'sanitised on write (PageController)',
        'public/page/show.blade.php' => 'sanitised on write (PageController)',
        'public/courses/show.blade.php' => 'sanitised on write (Admin PublicSite CourseController)',

        'documents/course-certificate.blade.php' => 'system-generated: template body and QR svg',
        'documents/id-card.blade.php' => 'system-generated: QR svg',

        'public/research/show.blade.php' => 'sanitised on write (SaveResearchPostAction)',
        'public/articles/show.blade.php' => 'sanitised on write (SaveResearchPostAction — same posts table)',
        'public/news/show.blade.php' => 'sanitised on write (SaveResearchPostAction — same posts table)',
        'public/about/index.blade.php' => 'sanitised on write (PageController)',
        'public/events/show.blade.php' => 'sanitised on write (SaveEventAction); requirements is an array, iterated and escaped',
        'announcements/index.blade.php' => 'sanitised on write (AnnouncementController, 3 locales)',
        'announcements/show.blade.php' => 'sanitised on write (AnnouncementController, 3 locales)',

        // Library items are the lowest-privilege authored HTML in the app:
        // written by approved writers, and any authed user may apply to be one.
        // Both the admin and writer paths funnel through SaveLibraryItemAction,
        // and the reader's pages are chunked from the same sanitised body.
        'public/library/show.blade.php' => 'sanitised on write (SaveLibraryItemAction)',
        'public/library/reader.blade.php' => 'sanitised on write (pages chunked from the sanitised item body)',

        // Not a gap and not sanitised: a legacy screen whose columns
        // (subjects.description_arabic / _dhivehi) are written by nothing but
        // DemoDataCommand. There is no user-facing path to reach them.
        'e-learning/show.blade.php' => 'no write path: legacy screen, seed-only columns',
    ];

    // Blade's own helpers are markup the framework emits, not authored content.
    $frameworkHelpers = '/csrf_field|method_field|->links\(\)|json_encode|@json|Js::|Str::markdown/';

    $root = resource_path('views');
    $found = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($files as $file) {
        if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());
        preg_match_all('/\{!!(.+?)!!\}/s', $source, $matches);

        foreach ($matches[1] as $expression) {
            if (preg_match($frameworkHelpers, $expression)) {
                continue;
            }
            $found[] = str_replace($root.'/', '', $file->getPathname());
        }
    }

    $found = array_values(array_unique($found));
    sort($found);
    $known = array_keys($declared);
    sort($known);

    expect($found)->toBe(
        $known,
        "The set of Blade views rendering raw HTML changed.\n"
        ."Every one must be declared with why it is acceptable:\n"
        ."  - sanitised on write (preferred: the stored value is safe for every reader)\n"
        ."  - system-generated (no user input reaches it)\n"
        ."  - UNSANITISED (a known gap, named so it is not mistaken for handled)\n"
        ."Found:\n  ".implode("\n  ", $found)
        ."\nDeclared:\n  ".implode("\n  ", $known)
    );
});
