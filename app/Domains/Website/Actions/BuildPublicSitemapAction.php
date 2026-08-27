<?php

namespace App\Domains\Website\Actions;

use App\Domains\Courses\Actions\ListPublicCourseSitemapEntriesAction;
use App\Domains\Website\Enums\DailyContentStatus;
use App\Domains\Website\Enums\DailyContentType;
use App\Domains\Website\Models\DailyContent;
use App\Domains\Website\Models\Event;
use App\Domains\Website\Models\GalleryAlbum;
use App\Domains\Website\Models\Post;

class BuildPublicSitemapAction
{
    /**
     * XML sitemap with xhtml hreflang triplets for courses, articles, news, research, events.
     */
    public function execute(): string
    {
        $locales = array_keys(config('laravellocalization.supportedLocales', [
            'en' => [],
            'ar' => [],
            'dv' => [],
        ]));
        $base = rtrim((string) config('app.url'), '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'."\n";

        $xml .= $this->urlElement($base.'/', now()->toDateString(), '1.0', 'daily', $this->homeAlternates($base, $locales));

        foreach ($locales as $locale) {
            $xml .= $this->urlElement(
                $base.'/'.$locale,
                now()->toDateString(),
                '1.0',
                'daily',
                $this->homeAlternates($base, $locales),
            );
        }

        $static = [
            'courses' => ['0.8', 'weekly'],
            'articles' => ['0.7', 'weekly'],
            'news' => ['0.8', 'weekly'],
            'research' => ['0.7', 'weekly'],
            'events' => ['0.8', 'weekly'],
            'gallery' => ['0.7', 'weekly'],
            'admissions' => ['0.9', 'weekly'],
            'contact' => ['0.6', 'weekly'],
        ];
        foreach ($static as $path => [$priority, $changefreq]) {
            $xml .= $this->localizedGroup($base, $locales, $path, now()->subDay()->toDateString(), $priority, $changefreq);
        }

        foreach (Post::query()->published()->articles()->latest('updated_at')->get(['slug', 'updated_at']) as $post) {
            $xml .= $this->localizedGroup($base, $locales, 'articles/'.$post->slug, $this->lastmod($post->updated_at), '0.7', 'monthly');
        }

        foreach (Post::query()->published()->news()->latest('updated_at')->get(['slug', 'updated_at']) as $post) {
            $xml .= $this->localizedGroup($base, $locales, 'news/'.$post->slug, $this->lastmod($post->updated_at), '0.7', 'monthly');
        }

        foreach (Post::query()->published()->research()->latest('updated_at')->get(['slug', 'updated_at']) as $post) {
            $xml .= $this->localizedGroup($base, $locales, 'research/'.$post->slug, $this->lastmod($post->updated_at), '0.7', 'monthly');
        }

        foreach (app(ListPublicCourseSitemapEntriesAction::class)->execute() as $course) {
            $xml .= $this->localizedGroup($base, $locales, 'courses/'.$course['slug'], $course['lastmod'], '0.8', 'monthly');
        }

        foreach (Event::query()->published()->public()->latest('updated_at')->get(['id', 'updated_at']) as $event) {
            $xml .= $this->localizedGroup($base, $locales, 'events/'.$event->id, $this->lastmod($event->updated_at), '0.6', 'monthly');
        }

        foreach (GalleryAlbum::query()->published()->public()->latest('updated_at')->get(['id', 'updated_at']) as $album) {
            $xml .= $this->localizedGroup($base, $locales, 'gallery/'.$album->id, $this->lastmod($album->updated_at), '0.5', 'monthly');
        }

        foreach (DailyContentType::cases() as $type) {
            $xml .= $this->localizedGroup($base, $locales, 'daily/'.$type->value, now()->toDateString(), '0.6', 'daily');
        }

        foreach (DailyContent::query()->where('status', DailyContentStatus::Published)->orderBy('publish_date')->get(['content_type', 'publish_date', 'updated_at']) as $daily) {
            $type = $daily->content_type instanceof DailyContentType ? $daily->content_type->value : (string) $daily->content_type;
            $date = $daily->publish_date?->toDateString();
            if ($date === null) {
                continue;
            }
            $xml .= $this->localizedGroup($base, $locales, 'daily/'.$type.'/'.$date, $this->lastmod($daily->updated_at), '0.7', 'monthly');
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * @param  list<string>  $locales
     */
    private function localizedGroup(string $base, array $locales, string $path, string $lastmod, string $priority, string $changefreq): string
    {
        $alternates = $this->alternates($base, $locales, $path);
        $xml = '';
        foreach ($locales as $locale) {
            $xml .= $this->urlElement($alternates[$locale], $lastmod, $priority, $changefreq, $alternates);
        }

        return $xml;
    }

    /**
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    private function homeAlternates(string $base, array $locales): array
    {
        $hrefs = [];
        foreach ($locales as $locale) {
            $hrefs[$locale] = $base.'/'.$locale;
        }

        return $hrefs;
    }

    /**
     * @param  list<string>  $locales
     * @return array<string, string>
     */
    private function alternates(string $base, array $locales, string $path): array
    {
        $hrefs = [];
        foreach ($locales as $locale) {
            $hrefs[$locale] = $base.'/'.$locale.'/'.$path;
        }

        return $hrefs;
    }

    /**
     * @param  array<string, string>  $alternates
     */
    private function urlElement(string $loc, string $lastmod, string $priority, string $changefreq, array $alternates): string
    {
        $xml = '<url>';
        $xml .= '<loc>'.$this->escape($loc).'</loc>';
        $xml .= '<lastmod>'.$this->escape($lastmod).'</lastmod>';
        $xml .= '<changefreq>'.$changefreq.'</changefreq>';
        $xml .= '<priority>'.$priority.'</priority>';
        foreach ($alternates as $locale => $href) {
            $xml .= '<xhtml:link rel="alternate" hreflang="'.$this->escape((string) $locale).'" href="'.$this->escape($href).'"/>';
        }
        $default = $alternates['en'] ?? (array_values($alternates)[0] ?? $loc);
        $xml .= '<xhtml:link rel="alternate" hreflang="x-default" href="'.$this->escape($default).'"/>';
        $xml .= '</url>'."\n";

        return $xml;
    }

    private function lastmod(mixed $value): string
    {
        if (is_object($value) && method_exists($value, 'toDateString')) {
            return $value->toDateString();
        }

        return now()->toDateString();
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
