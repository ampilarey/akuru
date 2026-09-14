<?php

/**
 * Every place the app renders HTML without escaping it is declared, one entry
 * per sink, with the write path that makes it safe.
 *
 * Blade escapes `{{ }}` and does not escape `{!! !!}`; React escapes everything
 * except `dangerouslySetInnerHTML`. Each of these is a deliberate decision to
 * render HTML as HTML, and **both surfaces are swept** — the Blade-only version
 * of this gate could not see the four React sinks at all, and the new UI is
 * React. Some are fine — a QR code or a barcode the system generated. Others are author-written content, and those are only safe
 * if something sanitised them on the way in.
 *
 * Originally nothing did: `PageController` validated `body` as
 * `required|string` and the public page rendered it raw, on a surface an
 * anonymous visitor sees and a `supervisor` can author. Every authored surface
 * now sanitises on write, and this keeps the list honest.
 *
 * ## Why this became one entry per sink (2026-09-14)
 *
 * It used to be keyed by **file**, with one justification each, and that is
 * how a live hole stayed green for months. `documents/course-certificate.blade.php`
 * has two sinks of different kinds — a template body somebody authors and a QR
 * SVG we generate — and its single declaration read *"system-generated:
 * template body and QR svg"*. True of the QR. False of the body, which
 * `course_creator` writes through `SaveCertificateTemplateAction` and which was
 * sanitised with `strip_tags($body, '<p><br>…')` — a call that removes
 * disallowed tags and keeps **every attribute** on the ones it allows, so
 * `<p onmouseover="…">` reached the rendered certificate intact.
 *
 * The declaration was doing the work of the analysis, and it was wrong. Two
 * things changed as a result:
 *
 *  - **One entry per sink**, so a second kind of content cannot shelter behind
 *    the first one's reason.
 *  - **The reason must name the writer** — an Action or controller calling
 *    `HtmlSanitizer` — rather than asserting that the value is safe. A claim
 *    cannot be checked by the next reader; a file name can.
 *
 * `StripTagsIsNotASanitiserTest` is the gate that would have caught the
 * underlying bug, and exists now for that reason.
 *
 * Filesystem only. No database, no HTTP.
 */
it('declares every raw HTML render, in Blade and in React', function () {
    $declared = require __DIR__.'/Baselines/raw_html_renders.php';

    $found = rawHtmlRenderSites();

    $new = array_values(array_diff(array_keys($found), array_keys($declared)));
    sort($new);

    expect($new)->toBeEmpty(
        "These render a value without escaping it and are not declared:\n  "
        .implode("\n  ", array_map(fn ($k) => $k.'  —  '.$found[$k], $new))
        ."\n\nUse `{{ }}` unless the value is genuinely HTML. If it is, add the line to "
        ."tests/Architecture/Baselines/raw_html_renders.php with one of:\n"
        ."  - sanitised on write — NAME the Action or controller calling HtmlSanitizer\n"
        ."  - system-generated — the application composed the markup; no user input reaches it\n"
        ."  - UNSANITISED — a known gap, named so it is not mistaken for handled\n"
        .'A reason that asserts safety without naming a write path is how the certificate '
        .'hole stayed green; see this file\'s header.'
    );

    $stale = array_values(array_diff(array_keys($declared), array_keys($found)));
    sort($stale);

    expect($stale)->toBeEmpty(
        "These declarations no longer match a raw render — delete them:\n  "
        .implode("\n  ", $stale)
        ."\n\nThe list may only shrink. If an expression was merely reworded, "
        .'re-point the entry; if the sink is gone, delete it.'
    );
});

/**
 * `<file> :: <expression>` => the whole line, for every unescaped render.
 *
 * Keyed by expression rather than line number: a line number breaks whenever
 * somebody edits the line above, which trains people to re-point the baseline
 * without reading it.
 *
 * @return array<string, string>
 */
function rawHtmlRenderSites(): array
{
    // Blade's own helpers are markup the framework emits, not authored content.
    $frameworkHelpers = '/csrf_field|method_field|->links\(\)|@json|Js::|Str::markdown/';

    $sites = [];

    foreach ([
        [resource_path('views'), '.blade.php', '/\{!!(.+?)!!\}/'],
        // The Blade sweep alone would miss the new UI entirely.
        [resource_path('js'), '.jsx', '/dangerouslySetInnerHTML=\{\{\s*__html:(.+?)\}\}/'],
    ] as [$root, $extension, $pattern]) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), $extension)) {
                continue;
            }

            $label = str_replace($root.'/', '', $file->getPathname());

            foreach (file($file->getPathname()) as $line) {
                if (preg_match($frameworkHelpers, $line) || ! preg_match_all($pattern, $line, $found)) {
                    continue;
                }

                foreach ($found[1] as $expression) {
                    $key = $label.' :: '.trim(preg_replace('/\s+/', ' ', $expression));
                    $sites[$key] = trim(preg_replace('/\s+/', ' ', $line));
                }
            }
        }
    }

    ksort($sites);

    return $sites;
}
