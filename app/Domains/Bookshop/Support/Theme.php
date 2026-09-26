<?php

namespace App\Domains\Bookshop\Support;

use App\Domains\Bookshop\Models\Vendor;

/**
 * A storefront theme as data (BOOKSHOP_PLAN §6.2): colours, fonts, scale,
 * shape. One place knows the defaults, what a vendor may choose, which
 * colour pairs must read, and how a theme becomes CSS custom properties on
 * the storefront root — no vendor CSS ever reaches the page.
 */
final class Theme
{
    public const COLORS = ['primary', 'secondary', 'accent', 'page_bg', 'card_bg', 'text', 'on_primary', 'on_accent'];

    public const SCALES = ['compact', 'regular', 'large'];

    public const SHAPES = [
        'radius' => ['square', 'soft', 'round'],
        'button' => ['filled', 'outlined'],
        'card' => ['flat', 'shadow', 'bordered'],
        'banner_height' => ['short', 'regular', 'tall'],
        'image_ratio' => ['square', 'portrait', 'landscape'],
    ];

    /** The pairs that must read, and the floor each must clear. */
    public const PAIRS = [
        ['text', 'page_bg', Contrast::TEXT],
        ['text', 'card_bg', Contrast::TEXT],
        ['on_primary', 'primary', Contrast::TEXT],
        ['on_accent', 'accent', Contrast::TEXT],
        ['accent', 'page_bg', Contrast::LARGE],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function default(): array
    {
        return [
            'preset' => 'ocean',
            'colors' => self::preset('ocean')['colors'],
            'fonts' => ['heading' => 'Inter', 'body' => 'Inter', 'accent' => null, 'dhivehi' => 'Faruma', 'arabic' => 'Noto Naskh Arabic'],
            'scale' => 'regular',
            'shape' => ['radius' => 'soft', 'button' => 'filled', 'card' => 'bordered', 'banner_height' => 'regular', 'image_ratio' => 'square'],
            'dark' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function preset(string $key): array
    {
        return (array) config('bookshop.storefront.presets.'.$key, []);
    }

    /**
     * The presets this vendor may use (decision 10: Akuru's palette needs the
     * partner badge).
     *
     * @return array<string, array{label: string, colors: array<string, string>, locked: bool}>
     */
    public static function presetsFor(Vendor $vendor): array
    {
        $out = [];
        foreach ((array) config('bookshop.storefront.presets', []) as $key => $preset) {
            $badge = $preset['badge'] ?? null;
            $out[$key] = [
                'label' => (string) $preset['label'],
                'colors' => (array) $preset['colors'],
                'locked' => $badge !== null && ! $vendor->hasBadge($badge),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function fonts(): array
    {
        $fonts = (array) config('bookshop.storefront.fonts', []);

        return ['latin' => (array) ($fonts['latin'] ?? []), 'dhivehi' => (array) ($fonts['dhivehi'] ?? []), 'arabic' => (array) ($fonts['arabic'] ?? [])];
    }

    /**
     * What the vendor sent, made whole and kept to the allowed choices;
     * anything unknown falls back to the default. Colours are normalised
     * hex. A preset name fills the colours unless custom colours came too.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input, Vendor $vendor): array
    {
        $default = self::default();
        $fonts = self::fonts();

        $presetKey = is_string($input['preset'] ?? null) && self::preset($input['preset']) !== [] ? $input['preset'] : null;
        $colors = [];
        foreach (self::COLORS as $slot) {
            $sent = Contrast::normalize(is_string($input['colors'][$slot] ?? null) ? $input['colors'][$slot] : null);
            $colors[$slot] = $sent ?? ($presetKey !== null ? self::preset($presetKey)['colors'][$slot] : $default['colors'][$slot]);
        }
        // The preset is whichever one the colours match — so a palette edited
        // away from a preset is the vendor's own, and the defaults (or a
        // preset typed in by hand) still say which preset they are.
        $presetKey = null;
        foreach ((array) config('bookshop.storefront.presets', []) as $key => $preset) {
            if ($colors === (array) $preset['colors']) {
                $presetKey = $key;
                break;
            }
        }

        $pick = fn (mixed $value, array $allowed, ?string $fallback) => is_string($value) && in_array($value, $allowed, true) ? $value : $fallback;
        $shape = [];
        foreach (self::SHAPES as $key => $allowed) {
            $shape[$key] = $pick($input['shape'][$key] ?? null, $allowed, $default['shape'][$key]);
        }

        $dark = null;
        if (is_array($input['dark'] ?? null) && ($input['dark']['enabled'] ?? false)) {
            $darkColors = [];
            foreach (self::COLORS as $slot) {
                $darkColors[$slot] = Contrast::normalize(is_string($input['dark']['colors'][$slot] ?? null) ? $input['dark']['colors'][$slot] : null)
                    ?? self::derivedDark($colors)[$slot];
            }
            $dark = ['enabled' => true, 'colors' => $darkColors];
        }

        return [
            'preset' => $presetKey,
            'colors' => $colors,
            'fonts' => [
                'heading' => $pick($input['fonts']['heading'] ?? null, $fonts['latin'], $default['fonts']['heading']),
                'body' => $pick($input['fonts']['body'] ?? null, $fonts['latin'], $default['fonts']['body']),
                'accent' => $pick($input['fonts']['accent'] ?? null, $fonts['latin'], null),
                'dhivehi' => $pick($input['fonts']['dhivehi'] ?? null, $fonts['dhivehi'], $default['fonts']['dhivehi']),
                'arabic' => $pick($input['fonts']['arabic'] ?? null, $fonts['arabic'], $default['fonts']['arabic']),
            ],
            'scale' => $pick($input['scale'] ?? null, self::SCALES, $default['scale']),
            'shape' => $shape,
            'dark' => $dark,
        ];
    }

    /**
     * Every pair that fails, with the ratio it reached and the floor it
     * needed — the reason the designer shows (§6.2). Empty means readable.
     *
     * @param  array<string, mixed>  $theme
     * @return list<array{pair: string, ratio: float, needed: float, scheme: string}>
     */
    public static function problems(array $theme): array
    {
        $problems = [];
        $schemes = ['light' => (array) $theme['colors']];
        if (($theme['dark']['enabled'] ?? false) === true) {
            $schemes['dark'] = (array) $theme['dark']['colors'];
        }
        foreach ($schemes as $scheme => $colors) {
            foreach (self::PAIRS as [$fg, $bg, $needed]) {
                $ratio = Contrast::ratio($colors[$fg], $colors[$bg]);
                if ($ratio < $needed) {
                    $problems[] = ['pair' => $fg.'/'.$bg, 'ratio' => $ratio, 'needed' => $needed, 'scheme' => $scheme];
                }
            }
        }

        return $problems;
    }

    /**
     * A dark variant derived from the light palette (§6.2 "or lets the theme
     * derive one"): the page and cards go dark, the text light, the brand
     * colours stay.
     *
     * @param  array<string, string>  $colors
     * @return array<string, string>
     */
    public static function derivedDark(array $colors): array
    {
        $page = '#15151A';
        // A dark accent (Ink's blue, say) vanishes on a dark page: lighten it
        // until it reads, then give it whichever text reads better on it.
        $accent = $colors['accent'];
        for ($i = 0; $i < 8 && Contrast::ratio($accent, $page) < Contrast::LARGE; $i++) {
            $accent = Contrast::lighten($accent, 0.15);
        }
        $onAccent = $accent === $colors['accent'] && Contrast::ratio($colors['on_accent'], $accent) >= Contrast::TEXT
            ? $colors['on_accent']
            : (Contrast::ratio('#FFFFFF', $accent) >= Contrast::ratio('#15151A', $accent) ? '#FFFFFF' : '#15151A');

        return [
            'primary' => $colors['primary'],
            'secondary' => $colors['secondary'],
            'accent' => $accent,
            'page_bg' => $page,
            'card_bg' => '#22222A',
            'text' => '#F3F3F6',
            'on_primary' => $colors['on_primary'],
            'on_accent' => $onAccent,
        ];
    }

    /**
     * The Google Fonts stylesheet for the theme's faces, or null when every
     * face is self-hosted.
     *
     * @param  array<string, mixed>  $theme
     */
    public static function fontsUrl(array $theme): ?string
    {
        $selfHosted = (array) config('bookshop.storefront.fonts.self_hosted', []);
        $families = array_values(array_unique(array_filter([
            $theme['fonts']['heading'] ?? null, $theme['fonts']['body'] ?? null, $theme['fonts']['accent'] ?? null,
            $theme['fonts']['dhivehi'] ?? null, $theme['fonts']['arabic'] ?? null,
        ], fn ($f) => is_string($f) && $f !== '' && ! in_array($f, $selfHosted, true))));
        if ($families === []) {
            return null;
        }

        return 'https://fonts.googleapis.com/css2?'.implode('&', array_map(fn (string $f) => 'family='.str_replace(' ', '+', $f).':wght@400;600;700', $families)).'&display=swap';
    }

    /**
     * The theme as CSS custom properties for the storefront root. Values
     * are normalised hex and words from fixed lists, so nothing here can
     * carry markup.
     *
     * @param  array<string, mixed>  $theme
     * @return array<string, string>
     */
    public static function cssVariables(array $theme): array
    {
        $c = (array) $theme['colors'];
        $stackEnd = "'Noto Sans Thaana', 'MV Boli', 'Noto Naskh Arabic', 'Amiri', system-ui, sans-serif";
        $font = fn (?string $f) => $f === null ? '' : "'".$f."', ";
        $radius = ['square' => '0', 'soft' => '0.5rem', 'round' => '1.25rem'][$theme['shape']['radius']];
        $scale = ['compact' => '0.94', 'regular' => '1', 'large' => '1.08'][$theme['scale']];
        $banner = ['short' => '10rem', 'regular' => '16rem', 'tall' => '24rem'][$theme['shape']['banner_height']];
        $ratio = ['square' => '1 / 1', 'portrait' => '3 / 4', 'landscape' => '4 / 3'][$theme['shape']['image_ratio']];

        return [
            '--sf-primary' => $c['primary'], '--sf-secondary' => $c['secondary'], '--sf-accent' => $c['accent'],
            '--sf-page' => $c['page_bg'], '--sf-card' => $c['card_bg'], '--sf-text' => $c['text'],
            '--sf-on-primary' => $c['on_primary'], '--sf-on-accent' => $c['on_accent'],
            '--sf-font-heading' => $font($theme['fonts']['heading']).$font($theme['fonts']['dhivehi']).$stackEnd,
            '--sf-font-body' => $font($theme['fonts']['body']).$font($theme['fonts']['dhivehi']).$stackEnd,
            '--sf-font-accent' => $font($theme['fonts']['accent'] ?? $theme['fonts']['body']).$stackEnd,
            '--sf-radius' => $radius, '--sf-scale' => $scale, '--sf-banner-h' => $banner, '--sf-image-ratio' => $ratio,
            '--sf-card-shadow' => $theme['shape']['card'] === 'shadow' ? '0 4px 14px rgba(0,0,0,.08)' : 'none',
            '--sf-card-border' => $theme['shape']['card'] === 'bordered' ? '1px solid rgba(0,0,0,.12)' : '1px solid transparent',
        ];
    }

    /**
     * The dark scheme's colour overrides, when the theme has one.
     *
     * @param  array<string, mixed>  $theme
     * @return array<string, string>
     */
    public static function darkCssVariables(array $theme): array
    {
        if (($theme['dark']['enabled'] ?? false) !== true) {
            return [];
        }
        $c = (array) $theme['dark']['colors'];

        return [
            '--sf-primary' => $c['primary'], '--sf-secondary' => $c['secondary'], '--sf-accent' => $c['accent'],
            '--sf-page' => $c['page_bg'], '--sf-card' => $c['card_bg'], '--sf-text' => $c['text'],
            '--sf-on-primary' => $c['on_primary'], '--sf-on-accent' => $c['on_accent'],
            '--sf-card-border' => $theme['shape']['card'] === 'bordered' ? '1px solid rgba(255,255,255,.14)' : '1px solid transparent',
        ];
    }
}
