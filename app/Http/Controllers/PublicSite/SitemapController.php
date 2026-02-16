<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\{Post, Course, Event, GalleryAlbum};

class SitemapController extends Controller
{
    public function index()
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $locales = array_keys(config('laravellocalization.supportedLocales'));
        $baseUrl = rtrim(config('app.url'), '/');

        // Home pages (include root and locale-prefixed)
        $sitemap .= $this->addUrl($baseUrl . '/', now(), '1.0', 'daily');
        foreach ($locales as $locale) {
            $sitemap .= $this->addUrl($baseUrl . '/' . $locale, now(), '1.0', 'daily');
        }

        // Static pages
        $staticPaths = ['courses', 'news', 'events', 'gallery', 'admissions', 'contact'];
        $priorities = ['courses' => '0.8', 'news' => '0.8', 'events' => '0.8', 'gallery' => '0.7', 'admissions' => '0.9', 'contact' => '0.6'];
        foreach ($locales as $locale) {
            foreach ($staticPaths as $path) {
                $sitemap .= $this->addUrl($baseUrl . '/' . $locale . '/' . $path, now()->subDays(1), $priorities[$path] ?? '0.7', 'weekly');
            }
        }

        // Posts
        foreach (Post::published()->latest('updated_at')->get() as $post) {
            foreach ($locales as $locale) {
                $sitemap .= $this->addUrl($baseUrl . '/' . $locale . '/news/' . $post->id, $post->updated_at, '0.7', 'monthly');
            }
        }

        // Courses
        foreach (Course::whereIn('status', ['open', 'upcoming'])->latest('updated_at')->get() as $course) {
            foreach ($locales as $locale) {
                $sitemap .= $this->addUrl($baseUrl . '/' . $locale . '/courses/' . $course->slug, $course->updated_at, '0.8', 'monthly');
            }
        }

        // Events
        foreach (Event::published()->public()->latest('updated_at')->get() as $event) {
            foreach ($locales as $locale) {
                $sitemap .= $this->addUrl($baseUrl . '/' . $locale . '/events/' . $event->id, $event->updated_at, '0.6', 'monthly');
            }
        }

        // Gallery albums
        foreach (GalleryAlbum::published()->public()->latest('updated_at')->get() as $album) {
            foreach ($locales as $locale) {
                $sitemap .= $this->addUrl($baseUrl . '/' . $locale . '/gallery/' . $album->id, $album->updated_at, '0.5', 'monthly');
            }
        }

        $sitemap .= '</urlset>';

        return response($sitemap, 200, [
            'Content-Type' => 'application/xml',
            'Charset' => 'UTF-8',
        ]);
    }

    private function addUrl(string $loc, $lastmod, string $priority, string $changefreq): string
    {
        $xml = '<url>';
        $xml .= '<loc>' . htmlspecialchars($loc) . '</loc>';
        $xml .= '<lastmod>' . (is_object($lastmod) ? $lastmod->format('Y-m-d') : date('Y-m-d', strtotime($lastmod))) . '</lastmod>';
        $xml .= '<changefreq>' . $changefreq . '</changefreq>';
        $xml .= '<priority>' . $priority . '</priority>';
        $xml .= '</url>' . "\n";
        return $xml;
    }
}
