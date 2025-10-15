<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\{HeroBanner, Post, Course, Testimonial, Faq, MediaGallery};
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $locale = app()->getLocale();
        
        // Fetch active hero banners for current locale
        $heroBanners = HeroBanner::where('is_active', true)
            ->where(function($query) use ($locale) {
                $query->where('locale', $locale)->orWhereNull('locale');
            })
            ->orderBy('order')
            ->take(3)
            ->get();

        // Latest news/posts
        $latestPosts = Post::published()
            ->with('author')
            ->latest('published_at')
            ->take(3)
            ->get();

        // Featured courses
        $featuredCourses = Course::open()
            ->with('category')
            ->take(6)
            ->get();

        // Upcoming events (using existing CalendarEvent model if available)
        $upcomingEvents = collect(); // Will be populated if CalendarEvent model exists
        if (class_exists(\App\Models\CalendarEvent::class)) {
            $upcomingEvents = \App\Models\CalendarEvent::where('date', '>=', now())
                ->where('audience', 'public')
                ->orderBy('date')
                ->take(4)
                ->get();
        }

        // Recent gallery items
        $galleryItems = collect();
        if (class_exists(\App\Models\MediaItem::class)) {
            $galleryItems = \App\Models\MediaItem::with('gallery')
                ->latest()
                ->take(6)
                ->get();
        }

        // Testimonials
        $testimonials = Testimonial::where('is_public', true)
            ->orderBy('order')
            ->take(3)
            ->get();

        // FAQs
        $faqs = Faq::where('is_public', true)
            ->orderBy('order')
            ->take(6)
            ->get();

        return view('public.home', compact(
            'heroBanners',
            'latestPosts', 
            'featuredCourses',
            'upcomingEvents',
            'galleryItems',
            'testimonials',
            'faqs'
        ));
    }
}
