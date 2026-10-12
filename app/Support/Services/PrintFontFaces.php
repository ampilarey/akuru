<?php

namespace App\Support\Services;

/**
 * NAME_TAGS_PLAN §5.4 (slice NT0): the `@font-face` rules for the print fonts
 * in `config/print.php`, for one of two readers — the browser, which fetches
 * them from `/fonts/print`, and headless Chrome, which opens the print HTML
 * as a file and so must be given each font by its `file://` path. Both read
 * the same files, so a tag previews and prints in the same face.
 */
class PrintFontFaces
{
    /** The rules for a page served by the app. */
    public static function forBrowser(): string
    {
        return self::css(fn (string $dir, string $file) => '/'.$dir.'/'.$file);
    }

    /** The rules for a page Chrome opens from disk. */
    public static function forFile(): string
    {
        return self::css(fn (string $dir, string $file) => 'file://'.public_path($dir.'/'.$file));
    }

    /**
     * @return array<string, array{family: string, label: string, scripts: list<string>, weights: list<int>}>
     */
    public static function faces(): array
    {
        $faces = [];
        foreach ((array) config('print.fonts', []) as $id => $font) {
            $faces[$id] = [
                'family' => (string) $font['family'],
                'label' => (string) ($font['label'] ?? $font['family']),
                'scripts' => array_values((array) ($font['scripts'] ?? [])),
                'weights' => array_map('intval', array_keys((array) $font['files'])),
            ];
        }

        return $faces;
    }

    /** Every font file and licence the table names, as paths under public/. */
    public static function files(): array
    {
        $paths = [];
        foreach ((array) config('print.fonts', []) as $font) {
            $dir = (string) ($font['dir'] ?? config('print.fonts_dir'));
            foreach ((array) $font['files'] as $file) {
                $paths[] = $dir.'/'.$file;
            }
            if (! empty($font['licence'])) {
                $paths[] = $dir.'/'.$font['licence'];
            }
        }

        return $paths;
    }

    private static function css(callable $url): string
    {
        $rules = [];
        foreach ((array) config('print.fonts', []) as $font) {
            $dir = (string) ($font['dir'] ?? config('print.fonts_dir'));
            foreach ((array) $font['files'] as $weight => $file) {
                $range = empty($font['unicode_range']) ? '' : ' unicode-range: '.$font['unicode_range'].';';
                $rules[] = sprintf(
                    "@font-face { font-family: '%s'; src: url('%s') format('woff2'); font-weight: %d; font-style: normal; font-display: block;%s }",
                    addslashes((string) $font['family']),
                    $url($dir, (string) $file),
                    (int) $weight,
                    $range,
                );
            }
        }

        return implode("\n", $rules);
    }
}
