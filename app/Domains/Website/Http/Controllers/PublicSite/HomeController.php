<?php

namespace App\Domains\Website\Http\Controllers\PublicSite;

use App\Domains\Courses\Actions\ComposeCourseConversionSignalsAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Website\Actions\ComposeHomepageDailyAction;
use App\Domains\Website\Actions\ComposeHomepageTrustAction;
use App\Domains\Website\Models\Event;
use App\Domains\Website\Models\GalleryAlbum;
use App\Domains\Website\Models\HeroBanner;
use App\Domains\Website\Models\Post;
use App\Domains\Website\Models\Testimonial;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $locale = app()->getLocale();

        // Cache courses/posts/events/stats for 10 minutes; gallery+testimonials are fetched fresh
        $cached = Cache::remember("homepage_data_v6_{$locale}", 600, function () use ($locale) {
            return $this->buildHomepageData($locale);
        });

        // These are always fetched fresh so they never appear missing due to stale cache
        $galleryPhotos = GalleryAlbum::with(['items' => fn ($q) => $q->where('file_type', 'image')->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->flatMap(fn ($album) => $album->items)
            ->take(12)
            ->values();

        $testimonials = Testimonial::where('is_public', true)
            ->whereNull('course_id')
            ->orderBy('order')
            ->take(8)
            ->get();

        $daily = app(ComposeHomepageDailyAction::class)->execute();

        return view('public.home', array_merge($cached, compact('galleryPhotos', 'testimonials', 'daily')));
    }

    private function buildHomepageData(string $locale): array
    {
        // Titles/descriptions per locale
        $titles = [
            'en' => ['title' => 'Welcome to Akuru Institute', 'desc' => 'Learn Quran, Arabic, and Islamic Studies in the Maldives'],
            'ar' => ['title' => 'مرحباً بكم في معهد أكورو', 'desc' => 'تعلم القرآن الكريم واللغة العربية والدراسات الإسلامية في المالديف'],
            'dv' => ['title' => 'އެކުރު އިންސްޓިއުޓުގައި ރައްކާ', 'desc' => 'ދިވެހިރާއްޖެ ގައި ޤުރުން، ޢަރަބި ބަހުން އަދި އިސްލާމީ ދަސްކަމުގެ ދެނެވިފައި'],
        ];
        $text = $titles[$locale] ?? $titles['en'];

        // Hero banners from DB or fallback
        $heroBanners = HeroBanner::where('is_active', true)
            ->where(function ($q) use ($locale) {
                $q->where('locale', $locale)->orWhereNull('locale');
            })
            ->orderBy('order')
            ->take(3)
            ->get();

        if ($heroBanners->isEmpty()) {
            $heroBanners = collect([
                (object) ['title' => __('public.Learn Quran with Expert Teachers'), 'subtitle' => __('public.Master the Holy Quran with our qualified instructors'), 'image_path' => 'hero-1.jpg', 'cta_text' => null, 'cta_url' => null],
                (object) ['title' => __('public.Arabic Language Courses'), 'subtitle' => __('public.Learn Arabic from beginner to advanced levels'), 'image_path' => 'hero-2.jpg', 'cta_text' => null, 'cta_url' => null],
                (object) ['title' => __('public.Islamic Studies Program'), 'subtitle' => __('public.Comprehensive Islamic education for all ages'), 'image_path' => 'hero-3.jpg', 'cta_text' => null, 'cta_url' => null],
            ]);
        }

        // Courses from DB — expired enrollment deadlines are hidden from Open Courses
        $courses = Course::openForPublicListing()
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->take(6)
            ->get();
        $signals = app(ComposeCourseConversionSignalsAction::class)->forCourses($courses);
        foreach ($courses as $course) {
            $course->setAttribute('conversion', $signals[$course->id] ?? null);
        }

        if ($courses->isEmpty()) {
            $courses = collect([
                (object) ['title' => __('public.Quran Memorization'), 'short_desc' => __('public.Complete Quran memorization program'), 'duration_text' => '2-3 years', 'cover_image' => null, 'category' => null, 'slug' => 'quran'],
                (object) ['title' => __('public.Arabic Language'), 'short_desc' => __('public.Learn Arabic from basics to fluency'), 'duration_text' => '1-2 years', 'cover_image' => null, 'category' => null, 'slug' => 'arabic'],
                (object) ['title' => __('public.Islamic Studies'), 'short_desc' => __('public.Comprehensive Islamic education'), 'duration_text' => '1 year', 'cover_image' => null, 'category' => null, 'slug' => 'islamic'],
            ]);
        }

        // News posts
        $posts = Post::published()
            ->where('type', 'news')
            ->with('category')
            ->latest('published_at')
            ->take(3)
            ->get();

        // Educational articles
        $articles = Post::published()
            ->where('type', 'article')
            ->with('category')
            ->latest('published_at')
            ->take(3)
            ->get();

        if ($posts->isEmpty()) {
            $posts = collect([
                (object) ['title' => __('public.New Academic Year Starts'), 'body' => __('public.Registration is now open for the new academic year...'), 'published_at' => now()->subDays(30), 'slug' => 'news-1'],
                (object) ['title' => __('public.Quran Competition Results'), 'body' => __('public.Congratulations to all participants...'), 'published_at' => now()->subDays(35), 'slug' => 'news-2'],
            ]);
        }

        // Events from DB
        $events = Event::published()
            ->public()
            ->where('start_date', '>=', now())
            ->orderBy('start_date')
            ->take(3)
            ->get();

        if ($events->isEmpty()) {
            $events = collect([
                (object) ['title' => __('public.Open House Day'), 'start_date' => now()->addDays(30), 'location' => __('public.Main Campus')],
                (object) ['title' => __('public.Quran Recitation Competition'), 'start_date' => now()->addDays(45), 'location' => __('public.Auditorium')],
            ]);
        }

        // Course/teacher counts stay local to this page. Students + years come from
        // trust settings (never invent a 500 / 5+ fallback).
        $courseCount = Course::whereIn('status', ['open', 'upcoming'])->count();
        $teacherCount = class_exists(\App\Domains\People\Models\Teacher::class) ? \App\Domains\People\Models\Teacher::count() : 0;
        $stats = [
            'courses' => $courseCount ?: 12,
            'teachers' => $teacherCount ?: 25,
        ];

        $trust = app(ComposeHomepageTrustAction::class)->execute($locale);

        return compact('text', 'heroBanners', 'courses', 'posts', 'articles', 'events', 'stats', 'trust');
    }
}
