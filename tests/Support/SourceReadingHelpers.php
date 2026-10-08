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

if (! function_exists('refusalEnglishIn')) {
    /**
     * A PHP file's English left where a page will read it (BACKLOG C19,
     * slices CT6b-2b and CT6b-2c): any string literal that reads as a
     * sentence, and any literal with words in it inside a `withMessages(...)`
     * call — where a refusal built of pieces ("This module still has " …
     * ". Move or delete those first.") hides from a sentence pattern.
     * Comments are other tokens, so an explanation does not count.
     *
     * @return list<string> "file:line text"
     */
    function refusalEnglishIn(string $file): array
    {
        $found = [];
        $depth = 0;
        $inside = false;

        foreach (token_get_all((string) file_get_contents(base_path($file))) as $token) {
            if (is_array($token) && $token[0] === T_STRING && $token[1] === 'withMessages') {
                $inside = true;
                $depth = 0;

                continue;
            }
            if ($inside && ($token === '(' || $token === '[')) {
                $depth++;
            } elseif ($inside && ($token === ')' || $token === ']')) {
                $depth--;
                if ($depth === 0) {
                    $inside = false;
                }
            }
            if (! is_array($token) || ! in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)) {
                continue;
            }

            $text = $token[0] === T_CONSTANT_ENCAPSED_STRING ? substr($token[1], 1, -1) : $token[1];
            $sentence = preg_match('/^[A-Z][\w\'’-]*( \S+)+[.!?]$/u', $text) === 1;
            $words = $inside && preg_match('/[A-Za-z]{2,} [A-Za-z]{2,}/', $text) === 1;
            if ($sentence || $words) {
                $found[] = "{$file}:{$token[2]} {$text}";
            }
        }

        return $found;
    }
}

if (! function_exists('refusalKeysIn')) {
    /**
     * Every whole phrase-book key a file names through `__()` or
     * `trans_choice()` — not the front of one it finishes with a code.
     *
     * @param  list<string>  $files
     * @return list<string>
     */
    function refusalKeysIn(array $files): array
    {
        $keys = [];
        foreach ($files as $file) {
            preg_match_all("/(?:__|trans_choice)\\('([a-z]+\\.[a-z_]+)'\\s*[,)]/", (string) file_get_contents(base_path($file)), $found);
            $keys = array_merge($keys, $found[1]);
        }

        return array_values(array_unique($keys));
    }
}

if (! function_exists('routerVisitsWithoutRow')) {
    /**
     * The lines of a page that visit with `router` (post, put, patch, delete)
     * and do not say which row they came from — a button whose refusal no
     * row would show (`useRowRefusals`, slice CT6b-2b).
     *
     * @return list<string> "path:line"
     */
    function routerVisitsWithoutRow(string $path): array
    {
        $found = [];
        foreach (explode("\n", (string) file_get_contents(base_path($path))) as $number => $line) {
            if (preg_match('/router\.(post|put|patch|delete)\(/', $line) && ! str_contains($line, 'actOn(')) {
                $found[] = "{$path}:".($number + 1);
            }
        }

        return $found;
    }
}

if (! function_exists('unnamedFields')) {
    /**
     * Fields a screen reader has no name for: no aria-label, no id for a label to
     * point at, and not inside a label. The course screens' check (slices
     * CT1–CT8), and the writer portal's (slice LT2).
     */
    function unnamedFields(string $source): array
    {
        preg_match_all('/<(select|input|textarea)\b(.*?)(\/>|<option|\.map\(|<\/select>)/s', $source, $fields, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $unnamed = [];
        foreach ($fields as $field) {
            [$attributes, $at] = $field[2];
            $before = substr($source, max(0, $at - 300), min(300, $at));
            $inLabel = strrpos($before, '<label') !== false && strrpos($before, '<label') > (int) strrpos($before, '</label>');
            if (! str_contains($attributes, 'aria-label=') && ! preg_match('/\sid=/', $attributes) && ! $inLabel && ! str_contains($attributes, 'type="hidden"')) {
                $unnamed[] = substr(preg_replace('/\s+/', ' ', trim($attributes)), 0, 80);
            }
        }

        return $unnamed;
    }
}
