<?php

namespace App\Domains\Bookshop\Support;

/**
 * A storefront's identity as data (BOOKSHOP_PLAN §6.1): names and taglines
 * in three languages, logo (light and dark), banner, the story, the
 * contact block, opening hours and social links. Image slots hold public
 * media ids; the story is rich text cleaned to the prose profile on save.
 */
final class Identity
{
    public const IMAGES = ['logo', 'logo_dark', 'banner'];

    public const SOCIALS = ['facebook', 'instagram', 'tiktok', 'youtube', 'x'];

    public const SOCIAL_HOSTS = [
        'facebook' => ['facebook.com', 'fb.com', 'm.facebook.com'],
        'instagram' => ['instagram.com'],
        'tiktok' => ['tiktok.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'x' => ['x.com', 'twitter.com'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function default(): array
    {
        return [
            'name_dv' => null, 'name_ar' => null,
            'tagline_dv' => null, 'tagline_ar' => null,
            'images' => ['logo' => null, 'logo_dark' => null, 'banner' => null],
            'story' => null, 'story_dv' => null, 'story_ar' => null,
            'contact' => ['phone' => null, 'email' => null, 'viber' => null, 'address' => null, 'map_url' => null],
            'hours' => null,
            'socials' => ['facebook' => null, 'instagram' => null, 'tiktok' => null, 'youtube' => null, 'x' => null],
        ];
    }

    /**
     * What the vendor sent, kept to the shape above. Image ids are kept from
     * `$current` unless the caller replaces or clears them. Text is trimmed;
     * the story is sanitised; social links must point at their own network.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>
     */
    public static function normalize(array $input, ?array $current): array
    {
        $current = ($current ?? []) + self::default();
        $text = fn (mixed $v, int $max) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;

        $socials = [];
        foreach (self::SOCIALS as $network) {
            $socials[$network] = self::socialUrl($network, $input['socials'][$network] ?? null);
        }

        return [
            'name_dv' => $text($input['name_dv'] ?? null, 120),
            'name_ar' => $text($input['name_ar'] ?? null, 120),
            'tagline_dv' => $text($input['tagline_dv'] ?? null, 200),
            'tagline_ar' => $text($input['tagline_ar'] ?? null, 200),
            'images' => (array) $current['images'],
            'story' => RichText::clean($input['story'] ?? null),
            'story_dv' => RichText::clean($input['story_dv'] ?? null),
            'story_ar' => RichText::clean($input['story_ar'] ?? null),
            'contact' => [
                'phone' => $text($input['contact']['phone'] ?? null, 40),
                'email' => filter_var(trim((string) ($input['contact']['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null,
                'viber' => $text($input['contact']['viber'] ?? null, 40),
                'address' => $text($input['contact']['address'] ?? null, 500),
                'map_url' => self::httpsUrl($input['contact']['map_url'] ?? null, ['google.com', 'maps.google.com', 'goo.gl', 'maps.app.goo.gl', 'openstreetmap.org']),
            ],
            'hours' => $text($input['hours'] ?? null, 500),
            'socials' => $socials,
        ];
    }

    /** Only https, only the network's own hosts; anything else is dropped. */
    public static function socialUrl(string $network, mixed $value): ?string
    {
        return self::httpsUrl($value, self::SOCIAL_HOSTS[$network] ?? []);
    }

    /**
     * @param  list<string>  $hosts
     */
    private static function httpsUrl(mixed $value, array $hosts): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (! str_starts_with($value, 'http')) {
            $value = 'https://'.$value;
        }
        $parts = parse_url($value);
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return null;
        }
        $host = strtolower(preg_replace('/^www\./', '', $parts['host']));
        foreach ($hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return mb_substr($value, 0, 300);
            }
        }

        return null;
    }
}
