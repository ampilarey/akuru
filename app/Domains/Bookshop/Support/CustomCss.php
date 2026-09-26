<?php

namespace App\Domains\Bookshop\Support;

/**
 * A shop's own CSS (slice B10c; the owner reversed BOOKSHOP_PLAN §6.8's "not
 * planned", ADR-039), made safe to put on its page.
 *
 * **Refused outright**, with a reason the shop sees: anything that fetches
 * from elsewhere or runs code — `url(`, `@import`, `image-set(`,
 * `expression(`, `javascript:`, `behavior:`, `-moz-binding`, `@font-face`,
 * `@charset`, `@namespace` — so a stylesheet can neither track a visitor
 * nor leak what is on the page (attribute selectors plus a background
 * image is the classic way); `<` and backslash escapes (no leaving the
 * `<style>` element, no spelling `url` in hex to slip past); `position:
 * fixed` (no covering the page — or the Akuru header and the cart — with
 * something else); unbalanced braces; more than `MAX_BYTES`.
 *
 * **Confined**: every selector is put under `.storefront`, the shop's own
 * part of its page — `html`, `body` and `:root` become `.storefront`
 * itself — inside `@media` and `@supports` too. `@keyframes` stay as they
 * are; other at-rules are refused. So the site's header, footer, cart and
 * checkout are out of reach, and nothing here ever runs on a page that is
 * not the shop's.
 */
final class CustomCss
{
    public const MAX_BYTES = 20000;

    private const FORBIDDEN = [
        '/url\s*\(/i' => 'url',
        '/image-set\s*\(/i' => 'url',
        '/@import/i' => 'import',
        '/expression\s*\(/i' => 'script',
        '/javascript\s*:/i' => 'script',
        '/behaviou?r\s*:/i' => 'script',
        '/-moz-binding/i' => 'script',
        '/@font-face/i' => 'font_face',
        '/@charset|@namespace/i' => 'at_rule',
        '/</' => 'markup',
        '/\\\\/' => 'escape',
        '/position\s*:\s*fixed/i' => 'fixed',
    ];

    private const NESTING = ['media', 'supports'];

    /**
     * @return array{css: string, errors: list<string>}
     */
    public static function clean(string $input): array
    {
        $css = str_replace(["\r\n", "\r"], "\n", $input);
        if (strlen($css) > self::MAX_BYTES) {
            return ['css' => '', 'errors' => ['too_long']];
        }
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        if (trim($css) === '') {
            return ['css' => '', 'errors' => []];
        }

        $errors = [];
        foreach (self::FORBIDDEN as $pattern => $reason) {
            if (preg_match($pattern, $css) === 1) {
                $errors[] = $reason;
            }
        }
        if ($errors !== []) {
            return ['css' => '', 'errors' => array_values(array_unique($errors))];
        }

        $blocks = self::blocks($css);
        if ($blocks === null) {
            return ['css' => '', 'errors' => ['braces']];
        }
        $out = self::scope($blocks, $errors);

        return $errors === [] ? ['css' => trim($out), 'errors' => []] : ['css' => '', 'errors' => array_values(array_unique($errors))];
    }

    /**
     * The top-level `prelude { body }` pairs, or null when braces or quotes
     * do not balance.
     *
     * @return list<array{0: string, 1: string}>|null
     */
    private static function blocks(string $css): ?array
    {
        $blocks = [];
        $depth = 0;
        $quote = null;
        $prelude = '';
        $body = '';
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            $c = $css[$i];
            if ($quote !== null) {
                $depth === 0 ? $prelude .= $c : $body .= $c;
                if ($c === $quote) {
                    $quote = null;
                }

                continue;
            }
            if ($c === '"' || $c === "'") {
                $quote = $c;
                $depth === 0 ? $prelude .= $c : $body .= $c;

                continue;
            }
            if ($c === '{') {
                if ($depth > 0) {
                    $body .= $c;
                }
                $depth++;

                continue;
            }
            if ($c === '}') {
                $depth--;
                if ($depth < 0) {
                    return null;
                }
                if ($depth === 0) {
                    $blocks[] = [trim($prelude), $body];
                    $prelude = '';
                    $body = '';
                } else {
                    $body .= $c;
                }

                continue;
            }
            $depth === 0 ? $prelude .= $c : $body .= $c;
        }
        if ($depth !== 0 || $quote !== null) {
            return null;
        }
        if (trim($prelude) !== '') {
            // Declarations outside any rule — nowhere for them to go.
            return null;
        }

        return $blocks;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $blocks
     * @param  list<string>  $errors
     */
    private static function scope(array $blocks, array &$errors): string
    {
        $out = '';
        foreach ($blocks as [$prelude, $body]) {
            if ($prelude === '') {
                $errors[] = 'selector';

                continue;
            }
            if (str_starts_with($prelude, '@')) {
                $name = strtolower((string) preg_replace('/^@([a-z-]+).*$/is', '$1', $prelude));
                if (in_array($name, self::NESTING, true)) {
                    $inner = self::blocks($body);
                    if ($inner === null) {
                        $errors[] = 'braces';

                        continue;
                    }
                    $out .= $prelude." {\n".self::scope($inner, $errors)."}\n";

                    continue;
                }
                if (in_array($name, ['keyframes', '-webkit-keyframes'], true)) {
                    $out .= $prelude.' {'.$body."}\n";

                    continue;
                }
                $errors[] = 'at_rule';

                continue;
            }
            if (str_contains($body, '{')) {
                $errors[] = 'nesting';

                continue;
            }
            $out .= implode(', ', array_map(self::scopeSelector(...), self::selectors($prelude))).' {'.self::declarations($body)."}\n";
        }

        return $out;
    }

    /**
     * A selector list split at its top-level commas.
     *
     * @return list<string>
     */
    private static function selectors(string $prelude): array
    {
        $parts = [];
        $depth = 0;
        $current = '';
        foreach (str_split($prelude) as $c) {
            if ($c === '(' || $c === '[') {
                $depth++;
            } elseif ($c === ')' || $c === ']') {
                $depth--;
            }
            if ($c === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }
            $current .= $c;
        }
        $parts[] = trim($current);

        return array_values(array_filter($parts, fn ($p) => $p !== ''));
    }

    private static function scopeSelector(string $selector): string
    {
        $selector = (string) preg_replace('/\s+/', ' ', trim($selector));
        // The page itself, for a shop, is its storefront.
        $rest = (string) preg_replace('/^(?::root|html(?:\s+body)?|body)(?=$|[\s.#:\[>+~])/i', '', $selector, 1, $replaced);
        if ($replaced > 0) {
            return '.storefront'.$rest;
        }
        if (str_starts_with($selector, '.storefront')) {
            return $selector;
        }

        return '.storefront '.$selector;
    }

    private static function declarations(string $body): string
    {
        $lines = [];
        foreach (explode(';', $body) as $declaration) {
            $declaration = trim((string) preg_replace('/\s+/', ' ', $declaration));
            if ($declaration !== '' && str_contains($declaration, ':')) {
                $lines[] = ' '.$declaration.';';
            }
        }

        return implode('', $lines).' ';
    }
}
