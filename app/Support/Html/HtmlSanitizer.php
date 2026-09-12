<?php

namespace App\Support\Html;

/**
 * Allowlist sanitiser for author-written HTML.
 *
 * Extracted from `ValidateContentBlockDataAction` (#280), where lesson blocks
 * were sanitised with `strip_tags`, which removes disallowed **tags** and keeps
 * every **attribute** on the ones it allows — so `<p onclick>` and
 * `<a href="javascript:…">` survived untouched.
 *
 * It lives in `Support` rather than a domain because two domains need it and
 * neither may import the other (rule 3): `Courses` sanitises lesson blocks,
 * `Website` sanitises CMS bodies. They want different allowlists, which is what
 * the profiles are for.
 *
 * Deliberately hand-rolled: the allowlists are small and fixed, so there is no
 * configuration surface to get wrong. **If they grow much beyond this, replace
 * this class with a real sanitiser library** rather than extending it.
 */
class HtmlSanitizer
{
    /**
     * Lesson content blocks. Deliberately spare: a lesson is prose.
     */
    public const PROFILE_LESSON = 'lesson';

    /**
     * CMS pages, articles, events. Richer, because a marketing page legitimately
     * wants headings, images, quotes and tables.
     */
    public const PROFILE_CMS = 'cms';

    /**
     * Elements removed **with their contents**, rather than unwrapped.
     *
     * Everything else disallowed is unwrapped, because the text inside a `<div>`
     * is the author's and should survive. What is inside these is not prose:
     * unwrapping `<script>alert(1)</script>` leaves `alert(1)` in the page as
     * loose text, which is what `strip_tags` used to do.
     */
    private const DROPPED_TAGS = [
        'script', 'style', 'iframe', 'object', 'embed', 'applet',
        'template', 'noscript', 'svg', 'math', 'form', 'input', 'button', 'select', 'textarea',
    ];

    /**
     * @return array{tags: list<string>, attributes: array<string, list<string>>}
     */
    private function profile(string $profile): array
    {
        return match ($profile) {
            self::PROFILE_CMS => [
                'tags' => [
                    'p', 'br', 'hr', 'strong', 'em', 'u', 's', 'blockquote', 'code', 'pre',
                    'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                    'a', 'img', 'figure', 'figcaption',
                    'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
                ],
                'attributes' => [
                    'a' => ['href', 'title'],
                    // No `srcset`, no `onerror`: an image carries a source and
                    // a description, nothing else.
                    'img' => ['src', 'alt', 'title'],
                    'th' => ['colspan', 'rowspan'],
                    'td' => ['colspan', 'rowspan'],
                ],
            ],
            default => [
                'tags' => ['p', 'br', 'strong', 'em', 'ul', 'ol', 'li', 'h2', 'h3', 'a'],
                'attributes' => ['a' => ['href']],
            ],
        };
    }

    public function clean(string $html, string $profile = self::PROFILE_LESSON): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $rules = $this->profile($profile);

        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // The wrapper stops DOMDocument inventing <html><body>; the encoding
        // hint keeps multibyte content (Dhivehi, Arabic) intact.
        $document->loadHTML(
            '<?xml encoding="UTF-8"?><div id="akuru-root">'.$html.'</div>',
            LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('akuru-root');
        if ($root === null) {
            return '';
        }

        $this->strip($root, $rules);

        $clean = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $clean .= $document->saveHTML($child);
        }

        return trim($clean);
    }

    /**
     * @param  array{tags: list<string>, attributes: array<string, list<string>>}  $rules
     */
    private function strip(\DOMNode $node, array $rules): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if (! $child instanceof \DOMElement) {
                // Comments, CDATA and processing instructions carry no content a
                // page needs, and can carry things it does not.
                $child->parentNode?->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROPPED_TAGS, true)) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            if (! in_array($tag, $rules['tags'], true)) {
                $this->strip($child, $rules);
                while ($child->firstChild !== null) {
                    $child->parentNode?->insertBefore($child->firstChild, $child);
                }
                $child->parentNode?->removeChild($child);

                continue;
            }

            $allowed = $rules['attributes'][$tag] ?? [];
            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->nodeName);

                if (! in_array($name, $allowed, true)) {
                    $child->removeAttribute($attribute->nodeName);

                    continue;
                }

                if (in_array($name, ['href', 'src'], true)
                    && ! $this->safeUrl((string) $attribute->nodeValue, $name)) {
                    $child->removeAttribute($attribute->nodeName);
                }
            }

            $this->strip($child, $rules);
        }
    }

    private function safeUrl(string $url, string $attribute): bool
    {
        // Entity-decoded and whitespace-stripped first: `java&#09;script:` and
        // ` javascript:` are the same link to a browser.
        $value = strtolower(preg_replace('/\s+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5)) ?? '');

        if ($value === '') {
            return false;
        }

        if (str_starts_with($value, '/') || ($attribute === 'href' && str_starts_with($value, '#'))) {
            return true;
        }

        if ($attribute === 'href' && str_starts_with($value, 'mailto:')) {
            return true;
        }

        return str_starts_with($value, 'https://') || str_starts_with($value, 'http://');
    }
}
