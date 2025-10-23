<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\{Post, Course, CalendarEvent, MediaGallery};
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index()
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>';
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        
        // Home page
        $sitemap .= $this->addUrl(route('home'), now(), '1.0', 'daily');
        
        // Static pages
        $staticPages = [
            'courses.index' => '0.8',
            'news.index' => '0.8',
            'events.index' => '0.8',
            'gallery.index' => '0.7',
            'admissions.create' => '0.9',
            'contact.create' => '0.6',
        ];
        
        foreach ($staticPages as $route => $priority) {
            $sitemap .= $this->addUrl(route($route), now()->subDays(1), $priority, 'weekly');
        }
        
        // Dynamic content - Posts
        $posts = Post::published()->latest('updated_at')->get();
        foreach ($posts as $post) {
            $sitemap .= $this->addUrl(route('news.show', $post), $post->updated_at, '0.7', 'monthly');
        }
        
        // Dynamic content - Courses
        $courses = Course::open()->latest('updated_at')->get();
        foreach ($courses as $course) {
            $sitemap .= $this->addUrl(route('courses.show', $course), $course->updated_at, '0.8', 'monthly');
        }
        
        // Dynamic content - Events
        if (class_exists(\App\Models\CalendarEvent::class)) {
            $events = CalendarEvent::where('audience', 'public')->latest('updated_at')->get();
            foreach ($events as $event) {
                $sitemap .= $this->addUrl(route('events.show', $event), $event->updated_at, '0.6', 'monthly');
            }
        }
        
        // Dynamic content - Gallery
        if (class_exists(\App\Models\MediaGallery::class)) {
            $galleries = MediaGallery::where('is_public', true)->latest('updated_at')->get();
            foreach ($galleries as $gallery) {
                $sitemap .= $this->addUrl(route('gallery.show', $gallery), $gallery->updated_at, '0.5', 'monthly');
            }
        }
        
        $sitemap .= '</urlset>';
        
        return response($sitemap, 200, [
            'Content-Type' => 'application/xml'
        ]);
    }
    
    private function addUrl($url, $lastmod, $priority, $changefreq)
    {
        $url = '<url>';
        $url .= '<loc>' . htmlspecialchars($url) . '</loc>';
        $url .= '<lastmod>' . $lastmod->format('Y-m-d') . '</lastmod>';
        $url .= '<changefreq>' . $changefreq . '</changefreq>';
        $url .= '<priority>' . $priority . '</priority>';
        
        // Add hreflang alternatives
        foreach (config('laravellocalization.supportedLocales') as $localeCode => $properties) {
            $localizedUrl = \LaravelLocalization::getLocalizedURL($localeCode, $url, [], true);
            $url .= '<xhtml:link rel="alternate" hreflang="' . $localeCode . '" href="' . htmlspecialchars($localizedUrl) . '" />';
        }
        
        $url .= '</url>';
        return $url;
    }
}
