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

if (! function_exists('stripPhpComments')) {
    /**
     * The same rule for PHP, via the tokenizer rather than a regex: PHP source
     * is full of `//` inside strings, and a regex would eat those too.
     *
     * String literals are deliberately kept. A class name or a facade call
     * spelled inside a string is still a reference — often a deliberately
     * dynamic one — and a source check that ignored strings would miss exactly
     * the cases worth catching.
     */
    function stripPhpComments(string $source): string
    {
        $out = '';

        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    // Keep the newlines so reported line numbers stay usable.
                    $out .= str_repeat("\n", substr_count($token[1], "\n"));

                    continue;
                }
                $out .= $token[1];

                continue;
            }

            $out .= $token;
        }

        return $out;
    }
}
