<?php

namespace App\Domains\Bookshop\Support;

use Illuminate\Validation\ValidationException;

/**
 * NAME_TAGS_PLAN §5.1 (NT2): the one design model — templates, free designs
 * and uploads share it. Millimetres on the tag; elements of five kinds; a
 * palette of colour schemes whose `{primary}` and `{secondary}` tokens an
 * element's colour may name; text fields `{name}`, `{class}`, … filled per
 * tag. `validate()` returns the design normalised, or refuses in the
 * page's language.
 */
final class PrintDesign
{
    public const VERSION = 1;

    public const TYPES = ['text', 'image', 'rect', 'ellipse', 'line'];

    public const ALIGNS = ['left', 'center', 'right'];

    public const DIRS = ['auto', 'ltr', 'rtl'];

    public const FITS = ['contain', 'cover'];

    public const MAX_ELEMENTS = 60;

    // PP3: a card's role, phone and email.
    public const FIELD_KEYS = ['name', 'class', 'school', 'subject', 'year', 'role', 'phone', 'email'];

    /** PP3: samples that are not words, so not in a phrase book. */
    public const SAMPLE_FIXED = ['phone' => '+960 777 1234', 'email' => 'nadha@akuru.edu.mv'];

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function validate(array $raw): array
    {
        $fonts = (array) config('print.fonts', []);
        $tag = $raw['tag'] ?? [];
        $w = self::mm($tag['w'] ?? 0);
        $h = self::mm($tag['h'] ?? 0);
        // PP1: no tag is larger than the largest sheet (A3).
        [$maxW, $maxH] = PrintSheet::largest();
        if ($w < 10 || $h < 10 || $w > $maxW || $h > $maxH) {
            self::refuse('tag', 'print.error_design_tag');
        }

        $background = $raw['background'] ?? [];
        $design = [
            'v' => self::VERSION,
            'tag' => ['w' => $w, 'h' => $h],
            'background' => [
                'color' => self::colour($background['color'] ?? '#FFFFFF', 'background') ?? '#FFFFFF',
                'image' => isset($background['image']) && $background['image'] !== null && $background['image'] !== '' ? (int) $background['image'] : null,
            ],
            'elements' => [],
            'palette' => self::palette($raw['palette'] ?? []),
            'fields' => [],
        ];

        $elements = $raw['elements'] ?? [];
        if (! is_array($elements) || count($elements) > self::MAX_ELEMENTS) {
            self::refuse('elements', 'print.error_design_elements', ['max' => self::MAX_ELEMENTS]);
        }
        $ids = [];
        $fieldKeys = [];
        foreach (array_values($elements) as $i => $el) {
            if (! is_array($el) || ! in_array($el['type'] ?? null, self::TYPES, true)) {
                self::refuse('elements', 'print.error_design_element_type', ['n' => $i + 1]);
            }
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($el['id'] ?? ''));
            if ($id === '' || isset($ids[$id])) {
                $id = 'e'.($i + 1).'-'.substr(md5((string) json_encode($el)), 0, 6);
            }
            $ids[$id] = true;
            $out = [
                'id' => $id,
                'type' => $el['type'],
                'x' => self::mm($el['x'] ?? 0),
                'y' => self::mm($el['y'] ?? 0),
                'w' => max(0.5, self::mm($el['w'] ?? 1)),
                'h' => max(0.5, self::mm($el['h'] ?? 1)),
                'rotate' => max(-360.0, min(360.0, round((float) ($el['rotate'] ?? 0), 1))),
                'locked' => (bool) ($el['locked'] ?? false),
            ];
            if ($out['x'] > $w || $out['y'] > $h) {
                self::refuse('elements', 'print.error_design_off_tag', ['n' => $i + 1]);
            }
            switch ($el['type']) {
                case 'text':
                    $font = (string) ($el['font'] ?? '');
                    if (! isset($fonts[$font])) {
                        self::refuse('elements', 'print.error_design_font', ['n' => $i + 1]);
                    }
                    $weights = array_map('intval', array_keys((array) $fonts[$font]['files']));
                    $weight = (int) ($el['weight'] ?? 400);
                    $out += [
                        'text' => mb_substr(trim((string) ($el['text'] ?? '')), 0, 200),
                        'font' => $font,
                        'size' => max(4, min(72, (int) round((float) ($el['size'] ?? 12)))),
                        'weight' => in_array($weight, $weights, true) ? $weight : $weights[0],
                        'color' => self::colour($el['color'] ?? '#111111', 'elements') ?? '#111111',
                        'align' => in_array($el['align'] ?? null, self::ALIGNS, true) ? $el['align'] : 'center',
                        'dir' => in_array($el['dir'] ?? null, self::DIRS, true) ? $el['dir'] : 'auto',
                        'field' => null,
                    ];
                    if (! empty($el['field'])) {
                        $key = strtolower(preg_replace('/[^A-Za-z0-9_]/', '', (string) $el['field']));
                        if ($key === '' || strlen($key) > 30) {
                            self::refuse('elements', 'print.error_design_field', ['n' => $i + 1]);
                        }
                        $out['field'] = $key;
                        $fieldKeys[$key] = true;
                    }
                    break;
                case 'image':
                    $media = (int) ($el['media'] ?? 0);
                    if ($media < 1) {
                        self::refuse('elements', 'print.error_design_image', ['n' => $i + 1]);
                    }
                    $out += ['media' => $media, 'fit' => in_array($el['fit'] ?? null, self::FITS, true) ? $el['fit'] : 'contain'];
                    break;
                case 'rect':
                case 'ellipse':
                    $out += [
                        'fill' => self::colour($el['fill'] ?? null, 'elements'),
                        'stroke' => self::colour($el['stroke'] ?? null, 'elements'),
                        'stroke_mm' => max(0.0, min(5.0, round((float) ($el['stroke_mm'] ?? 0.3), 2))),
                        'radius' => $el['type'] === 'rect' ? max(0.0, min(50.0, round((float) ($el['radius'] ?? 0), 2))) : 0.0,
                    ];
                    break;
                case 'line':
                    $out += ['stroke' => self::colour($el['stroke'] ?? '#111111', 'elements') ?? '#111111'];
                    break;
            }
            $design['elements'][] = $out;
        }

        // Every field a text names is listed, with whether it must be filled.
        $required = [];
        foreach ((array) ($raw['fields'] ?? []) as $f) {
            if (is_array($f) && isset($f['key'])) {
                $required[strtolower((string) $f['key'])] = (bool) ($f['required'] ?? false);
            }
        }
        foreach (array_keys($fieldKeys) as $key) {
            $design['fields'][] = ['key' => $key, 'required' => $required[$key] ?? ($key === 'name')];
        }

        return $design;
    }

    /**
     * The design scaled to another tag size (NT3): a template drawn on 95 × 60
     * printed on a 63.5 × 38.1 label. Positions and sizes scale on their own
     * axis; a text's point size and an outline scale by the smaller factor,
     * so nothing grows out of its box.
     *
     * @param  array<string, mixed>  $design  a validated design
     * @return array<string, mixed>
     */
    public static function fit(array $design, float $w, float $h): array
    {
        $fromW = (float) ($design['tag']['w'] ?? $w);
        $fromH = (float) ($design['tag']['h'] ?? $h);
        if ($fromW <= 0 || $fromH <= 0 || (abs($fromW - $w) < 0.01 && abs($fromH - $h) < 0.01)) {
            return $design;
        }
        $sx = $w / $fromW;
        $sy = $h / $fromH;
        $s = min($sx, $sy);
        $design['tag'] = ['w' => round($w, 2), 'h' => round($h, 2)];
        foreach ($design['elements'] as $i => $el) {
            $el['x'] = round($el['x'] * $sx, 2);
            $el['y'] = round($el['y'] * $sy, 2);
            $el['w'] = max(0.5, round($el['w'] * $sx, 2));
            $el['h'] = max(0.5, round($el['h'] * $sy, 2));
            if (isset($el['size'])) {
                $el['size'] = max(4, (int) round($el['size'] * $s));
            }
            foreach (['stroke_mm', 'radius'] as $key) {
                if (isset($el[$key])) {
                    $el[$key] = round($el[$key] * $s, 2);
                }
            }
            $design['elements'][$i] = $el;
        }

        return $design;
    }

    /** The customer's choice of colour scheme, where the design offers it (NT3 field mode). */
    public static function withScheme(array $design, ?string $scheme): array
    {
        if ($scheme !== null && isset($design['palette']['schemes'][$scheme])) {
            $design['palette']['scheme'] = $scheme;
        }

        return $design;
    }

    /**
     * The customer's choice of font for the texts they fill (NT3 field mode):
     * only the field texts change, and only to a font the desk offers; the
     * weight falls back to one the font has.
     */
    public static function withFieldFont(array $design, ?string $font): array
    {
        $fonts = (array) config('print.fonts', []);
        if ($font === null || ! isset($fonts[$font])) {
            return $design;
        }
        $weights = array_map('intval', array_keys((array) $fonts[$font]['files']));
        foreach ($design['elements'] as $i => $el) {
            if (($el['type'] ?? null) === 'text' && ! empty($el['field'])) {
                $el['font'] = $font;
                $el['weight'] = in_array((int) ($el['weight'] ?? 400), $weights, true) ? (int) $el['weight'] : (in_array(700, $weights, true) && (int) ($el['weight'] ?? 400) >= 700 ? 700 : $weights[0]);
                $design['elements'][$i] = $el;
            }
        }

        return $design;
    }

    /** The font the field texts are set in now, or null when there is none. */
    public static function fieldFont(array $design): ?string
    {
        foreach ((array) ($design['elements'] ?? []) as $el) {
            if (($el['type'] ?? null) === 'text' && ! empty($el['field'])) {
                return (string) $el['font'];
            }
        }

        return null;
    }

    /** The keys the design asks the customer for, in the order they appear. */
    public static function fieldKeys(array $design): array
    {
        return array_values(array_map(fn (array $f) => (string) $f['key'], (array) ($design['fields'] ?? [])));
    }

    /** A colour's hex, or a palette token, or null for none. */
    private static function colour(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '' || $value === 'none') {
            return null;
        }
        $value = strtoupper(trim((string) $value));
        if (in_array($value, ['{PRIMARY}', '{SECONDARY}'], true)) {
            return strtolower($value);
        }
        if (preg_match('/^#[0-9A-F]{6}$/', $value)) {
            return $value;
        }
        self::refuse($field, 'print.error_design_colour');
    }

    /** @return array{scheme: string, schemes: array<string, array{primary: string, secondary: string}>} */
    private static function palette(mixed $raw): array
    {
        $schemes = [];
        foreach ((array) ($raw['schemes'] ?? []) as $name => $colours) {
            $name = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $name));
            if ($name === '' || ! is_array($colours) || count($schemes) >= 8) {
                continue;
            }
            $schemes[$name] = [
                'primary' => self::colour($colours['primary'] ?? '#7A1F2B', 'palette') ?? '#7A1F2B',
                'secondary' => self::colour($colours['secondary'] ?? '#E9D8B4', 'palette') ?? '#E9D8B4',
            ];
        }
        if ($schemes === []) {
            $schemes = ['maroon' => ['primary' => '#7A1F2B', 'secondary' => '#E9D8B4']];
        }
        $scheme = (string) ($raw['scheme'] ?? '');

        return ['scheme' => isset($schemes[$scheme]) ? $scheme : array_key_first($schemes), 'schemes' => $schemes];
    }

    private static function mm(mixed $value): float
    {
        return round(max(0.0, min(300.0, (float) $value)), 2);
    }

    /** @param  array<string, mixed>  $vars */
    private static function refuse(string $field, string $key, array $vars = []): never
    {
        throw ValidationException::withMessages([$field => __($key, $vars)]);
    }
}
