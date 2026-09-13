<?php

/**
 * Helpers for tests that assert about source files rather than behaviour.
 *
 * **Strip comments before searching.** This is not a nicety, and the codebase
 * has learned it twice. The `FOREIGN_KEY_CHECKS` guard once failed on the two
 * files that *quoted the old code in order to explain it*;
 * `PlatformApisStayInLayerTest` did the same on its first run; and so did the
 * KNOWN_ISSUES #17 assertion, on the JSX comment explaining the boolean it
 * replaced.
 *
 * A check that cannot tell documentation from instruction punishes writing the
 * explanation down — and the note saying why a rule exists is the first thing
 * to go.
 *
 * It lives here rather than in one of the test files because Pest loads every
 * test into one scope: a helper defined in a test file is global whether or not
 * that file was loaded, so `--filter` runs would find it missing (which is how
 * this one was found) and a second definition anywhere would be a fatal
 * redeclare.
 */
if (! function_exists('stripJsComments')) {
    /**
     * `//` is only treated as a comment when it does not follow a `:`, so the
     * `//` inside an `https://` URL survives.
     */
    function stripJsComments(string $source): string
    {
        $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        return (string) preg_replace('#(?<!:)//[^\n]*#', '', $source);
    }
}
