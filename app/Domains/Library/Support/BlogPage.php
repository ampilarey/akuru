<?php

namespace App\Domains\Library\Support;

use App\Domains\Library\Models\LibraryBlogTheme;
use App\Support\Theme\Contrast;

/**
 * BLOG_PLAN BL3a (§5): a blog page's design as data — a theme from the
 * gallery, an accent colour, a heading font, a tagline, the languages and the
 * sections in their order. One place knows what a writer may choose, which
 * colours must read, and how a design becomes CSS custom properties on the
 * page's root. No writer CSS, script or embed ever reaches the page (§12).
 */
final class BlogPage
{
    /** What the page shows, in the writer's order. Topics and tips come with BL4 and BL5. */
    public const SECTIONS = ['pinned', 'latest', 'series', 'works', 'about'];

    public const LANGUAGES = ['en', 'dv', 'ar'];

    /** Heading faces for Latin text; Thaana and Arabic always use the site's own. */
    public const FONTS = ['Inter', 'Merriweather', 'Lora', 'Poppins', 'Bree Serif'];

    public const TAGLINE_MAX = 120;

    public const ABOUT_MAX = 1500;

    /**
     * The design a profile shows now, made whole.
     *
     * @param  array<string, mixed>  $profile  the profile's live columns
     * @return array{theme: string, accent: ?string, font: ?string, tagline: ?string, about: ?string, languages: list<string>, sections: list<array{key: string, on: bool}>}
     */
    public static function live(array $profile): array
    {
        return [
            'theme' => (string) ($profile['theme_key'] ?? self::defaultTheme()),
            'accent' => $profile['accent_color'] ?? null,
            'font' => $profile['heading_font'] ?? null,
            'tagline' => $profile['tagline'] ?? null,
            'about' => $profile['bio'] ?? null,
            'languages' => self::languages($profile['languages'] ?? []),
            'sections' => self::sections($profile['sections'] ?? null),
        ];
    }

    /**
     * What the writer sent, kept to the allowed choices. Unknown values fall
     * back; text is trimmed to its limits and kept plain.
     *
     * @param  array<string, mixed>  $input
     * @return array{theme: string, accent: ?string, font: ?string, tagline: ?string, about: ?string, languages: list<string>, sections: list<array{key: string, on: bool}>}
     */
    public static function normalize(array $input): array
    {
        $theme = is_string($input['theme'] ?? null) && self::theme($input['theme']) !== null ? $input['theme'] : self::defaultTheme();
        $plain = fn (mixed $text, int $max) => ($t = trim(strip_tags((string) $text))) === '' ? null : mb_substr($t, 0, $max);

        return [
            'theme' => $theme,
            'accent' => Contrast::normalize(is_string($input['accent'] ?? null) ? $input['accent'] : null),
            'font' => is_string($input['font'] ?? null) && in_array($input['font'], self::FONTS, true) ? $input['font'] : null,
            'tagline' => $plain($input['tagline'] ?? null, self::TAGLINE_MAX),
            'about' => $plain($input['about'] ?? null, self::ABOUT_MAX),
            'languages' => self::languages($input['languages'] ?? []),
            'sections' => self::sections($input['sections'] ?? null),
        ];
    }

    /**
     * Why the colours do not read, or nothing. The accent must stand out on
     * the page and on a card (3:1, as links and buttons need), and the text
     * on the accent must read (4.5:1).
     *
     * @param  array{theme: string, accent: ?string}  $design
     * @return list<array{pair: string, ratio: float, needed: float}>
     */
    public static function problems(array $design): array
    {
        $colors = self::colors($design);
        $problems = [];
        foreach ([['accent', 'page_bg', Contrast::LARGE], ['accent', 'card_bg', Contrast::LARGE], ['on_accent', 'accent', Contrast::TEXT]] as [$fg, $bg, $needed]) {
            $ratio = Contrast::ratio($colors[$fg], $colors[$bg]);
            if ($ratio < $needed) {
                $problems[] = ['pair' => $fg.'/'.$bg, 'ratio' => $ratio, 'needed' => $needed];
            }
        }

        return $problems;
    }

    /**
     * The colours the page uses: the theme's, with the writer's accent and
     * whichever of white or near-black reads better on it.
     *
     * @param  array{theme: string, accent: ?string}  $design
     * @return array{page_bg: string, card_bg: string, text: string, accent: string, on_accent: string, radius: string}
     */
    public static function colors(array $design): array
    {
        $theme = self::theme($design['theme']) ?? self::theme(self::defaultTheme()) ?? self::fallback();
        $out = array_merge(self::fallback(), $theme);
        if (! empty($design['accent'])) {
            $out['accent'] = $design['accent'];
            $out['on_accent'] = Contrast::ratio('#FFFFFF', $design['accent']) >= Contrast::ratio('#15151A', $design['accent']) ? '#FFFFFF' : '#15151A';
        }

        return $out;
    }

    /**
     * The design as CSS custom properties for the blog's root. Hex colours
     * normalised by Contrast, words and font names from fixed lists: nothing
     * here can carry markup.
     *
     * @param  array{theme: string, accent: ?string, font: ?string}  $design
     * @return array<string, string>
     */
    public static function cssVariables(array $design): array
    {
        $c = self::colors($design);
        $stack = "'Faruma', 'Noto Sans Thaana', 'Noto Naskh Arabic', system-ui, sans-serif";

        return [
            '--blog-page' => $c['page_bg'], '--blog-card' => $c['card_bg'], '--blog-text' => $c['text'],
            '--blog-accent' => $c['accent'], '--blog-on-accent' => $c['on_accent'],
            '--blog-radius' => ['square' => '0', 'soft' => '0.5rem', 'round' => '1.25rem'][$c['radius']] ?? '0.5rem',
            '--blog-font-heading' => ($design['font'] ? "'".$design['font']."', " : '').$stack,
        ];
    }

    /** The Google Fonts stylesheet for a chosen Latin heading face, or null. */
    public static function fontsUrl(?string $font): ?string
    {
        return $font !== null && in_array($font, self::FONTS, true) && $font !== 'Inter'
            ? 'https://fonts.googleapis.com/css2?family='.str_replace(' ', '+', $font).':wght@600;700&display=swap'
            : null;
    }

    /** @return array<string, array<string, string>> the gallery's active themes, by key */
    public static function themes(): array
    {
        return LibraryBlogTheme::query()->where('active', true)->orderBy('sort_order')->get()
            ->mapWithKeys(fn (LibraryBlogTheme $theme) => [$theme->key => (array) $theme->theme])->all();
    }

    /** @return array<string, string>|null */
    public static function theme(string $key): ?array
    {
        return self::themes()[$key] ?? null;
    }

    public static function defaultTheme(): string
    {
        return array_key_first(self::themes()) ?? 'akuru';
    }

    /** @return list<string> */
    private static function languages(mixed $value): array
    {
        return array_values(array_intersect(self::LANGUAGES, is_array($value) ? $value : []));
    }

    /**
     * Every section exactly once: the writer's order first, the rest after,
     * each on unless switched off.
     *
     * @return list<array{key: string, on: bool}>
     */
    private static function sections(mixed $value): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [] as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : $row;
            if (is_string($key) && in_array($key, self::SECTIONS, true) && ! isset($out[$key])) {
                $out[$key] = ['key' => $key, 'on' => ! is_array($row) || filter_var($row['on'] ?? true, FILTER_VALIDATE_BOOL)];
            }
        }
        foreach (self::SECTIONS as $key) {
            $out[$key] ??= ['key' => $key, 'on' => true];
        }

        return array_values($out);
    }

    /** @return array{page_bg: string, card_bg: string, text: string, accent: string, on_accent: string, radius: string} */
    private static function fallback(): array
    {
        return ['page_bg' => '#FBF7F1', 'card_bg' => '#FFFFFF', 'text' => '#2B1B1E', 'accent' => '#7A1F2B', 'on_accent' => '#FFFFFF', 'radius' => 'soft'];
    }
}
