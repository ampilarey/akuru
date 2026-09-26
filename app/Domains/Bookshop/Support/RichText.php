<?php

namespace App\Domains\Bookshop\Support;

use App\Support\Html\HtmlSanitizer;

/**
 * Vendor-written prose, the one way (plan §10): plain text becomes
 * paragraphs, and everything is cleaned to the prose profile — paragraphs,
 * emphasis, lists, links; no script, style, iframe or attribute beyond
 * href. Used by product descriptions, the story and the sections.
 */
final class RichText
{
    public static function clean(mixed $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }
        if (! preg_match('/<(p|ul|ol|li|h[1-6]|br|div|blockquote)\b/i', $text)) {
            $paragraphs = preg_split('/\R{2,}/', $text) ?: [];
            $text = implode('', array_map(
                fn (string $p) => '<p>'.nl2br(str_contains($p, '<') ? trim($p) : e(trim($p)), false).'</p>',
                array_filter($paragraphs, fn (string $p) => trim($p) !== ''),
            ));
        }

        return app(HtmlSanitizer::class)->clean($text, HtmlSanitizer::PROFILE_LESSON);
    }
}
