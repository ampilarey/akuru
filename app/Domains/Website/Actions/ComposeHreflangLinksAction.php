<?php

namespace App\Domains\Website\Actions;

class ComposeHreflangLinksAction
{
    /**
     * Localized URL triplets (en/dv/ar) plus x-default for the current public page.
     *
     * @return list<array{hreflang: string, href: string}>
     */
    public function execute(?string $absoluteUrl = null): array
    {
        $absoluteUrl = $absoluteUrl ?: url()->current();
        $locales = array_keys(config('laravellocalization.supportedLocales', [
            'en' => [],
            'ar' => [],
            'dv' => [],
        ]));
        if ($locales === []) {
            return [];
        }

        $default = in_array('en', $locales, true) ? 'en' : $locales[0];
        $origin = $this->origin($absoluteUrl);
        $remainder = $this->pathWithoutLocale($absoluteUrl, $locales);

        $links = [];
        $hrefs = [];
        foreach ($locales as $locale) {
            $href = $origin.'/'.$locale.($remainder === '' ? '' : '/'.$remainder);
            $hrefs[$locale] = $href;
            $links[] = ['hreflang' => $locale, 'href' => $href];
        }
        $links[] = [
            'hreflang' => 'x-default',
            'href' => $hrefs[$default] ?? $hrefs[$locales[0]],
        ];

        return $links;
    }

    /**
     * @param  list<string>  $locales
     */
    private function pathWithoutLocale(string $absoluteUrl, array $locales): string
    {
        $path = parse_url($absoluteUrl, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $segments = array_values(array_filter(explode('/', $path), fn (string $part): bool => $part !== ''));
        if ($segments !== [] && in_array($segments[0], $locales, true)) {
            array_shift($segments);
        }

        return implode('/', $segments);
    }

    private function origin(string $absoluteUrl): string
    {
        $parts = parse_url($absoluteUrl);
        $scheme = $parts['scheme'] ?? parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'http';
        $host = $parts['host'] ?? parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
        $port = $parts['port'] ?? null;
        $origin = $scheme.'://'.$host;
        if ($port !== null && ! in_array((int) $port, [80, 443], true)) {
            $origin .= ':'.$port;
        }

        return $origin;
    }
}
