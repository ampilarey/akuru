<?php

/**
 * Every place the application starts a session is declared, with what the
 * caller proved first.
 *
 * ## Why `Auth::login()` in particular
 *
 * It was the vector for two P0s on the same day. In both, **the call itself
 * looked ordinary** — a user object fetched a few lines up, signed in. What
 * was wrong was upstream: the id came from `session('pending_user_id')`, which
 * a public route wrote from a phone number in a request body, at the moment an
 * OTP was *sent* rather than entered.
 *
 *  - #367: `setPassword` rewrote a stranger's password, name, date of birth
 *    and national ID.
 *  - #368: `enroll` and `continueForm` signed you in as them, then returned a
 *    redirect saying *"Please verify your contact first"* — which does not
 *    undo a login.
 *
 * Reading the call tells you nothing. Reading what authorised it is the whole
 * question, and nothing in the code makes anybody ask it. This gate does.
 *
 * ## What a declaration has to say
 *
 * The **proof**, not the intent. *"the user just registered"* is a story;
 * *"`Hash::check` against the submitted password, rate-limited"* is a fact the
 * next reader can go and verify. An entry that cannot point at a credential, a
 * verified one-time code, or an existing authenticated session is describing a
 * hole rather than excusing one.
 *
 * ## Keyed by method where a file has several
 *
 * `CourseRegistrationController` starts sessions in six places for five
 * different reasons. One entry per file would let the safest of them cover the
 * rest — exactly how `RawHtmlRendersAreDeclaredTest` missed a live XSS until it
 * was made per-sink.
 */
it('declares what was proved before every session it starts', function () {
    $declared = require __DIR__.'/Baselines/session_creations.php';

    $found = sessionCreationSites();

    $new = array_values(array_diff(array_keys($found), array_keys($declared)));
    sort($new);

    expect($new)->toBeEmpty(
        "These start a session and do not say what authorised it:\n  "
        .implode("\n  ", array_map(fn ($k) => $k.'  —  '.$found[$k], $new))
        ."\n\nAdd an entry to tests/Architecture/Baselines/session_creations.php naming the "
        .'**proof**: a credential checked, a one-time code verified, or an existing '
        .'authenticated session. Not the intent — "the user just registered" is a story, '
        ."\"Hash::check against the submitted password\" is a fact.\n"
        .'If you cannot name one, that is the finding. Read the #367/#368 note in this file first.'
    );

    $stale = array_values(array_diff(array_keys($declared), array_keys($found)));
    sort($stale);

    expect($stale)->toBeEmpty(
        "These declarations no longer match a session start — delete them:\n  "
        .implode("\n  ", $stale)
        ."\n\nThe list may only shrink. A login that has gone away is good news."
    );
});

/**
 * `path` or `path::method` => the line, for every call that starts a session.
 *
 * @return array<string, string>
 */
function sessionCreationSites(): array
{
    $sites = [];

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        // Comments mention these calls when explaining them, and an
        // explanation is not a login.
        $source = stripPhpComments(file_get_contents($file->getPathname()));
        $path = str_replace(base_path().'/', '', $file->getPathname());

        $pattern = '/(?:Auth::|auth\(\)->|Auth::guard\([^)]*\)->)(login|loginUsingId)\s*\(/';

        if (! preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            continue;
        }

        // Only key by method where a file has more than one, so single-login
        // files stay readable.
        $multiple = count($matches[0]) > 1;

        foreach ($matches[0] as $match) {
            $before = substr($source, 0, $match[1]);
            $key = $path;

            if ($multiple && preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $before, $fns)) {
                $key .= '::'.end($fns[1]);
            }

            $sites[$key] = trim($match[0]).'  (line '.(substr_count($before, "\n") + 1).')';
        }
    }

    ksort($sites);

    return $sites;
}
