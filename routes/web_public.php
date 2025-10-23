<?php

use App\Http\Controllers\PublicSite\{
    HomeController, 
    PostController, 
    CourseController, 
    AdmissionController, 
    GalleryController, 
    EventController, 
    PageController, 
    ContactController,
    SitemapController
};

// Home page
Route::get('/', [HomeController::class, 'index'])->name('public.home');

// Test pages (remove in production)
Route::get('/test', function() {
    return view('public.test');
})->name('public.test');

Route::get('/lang-test', function() {
    return view('public.lang-test');
})->name('public.lang-test');

// Static pages
Route::get('/about', [PageController::class, 'show'])->defaults('slug', 'about')->name('public.about');
Route::get('/page/{slug}', [PageController::class, 'show'])->name('public.page.show');

// Courses
Route::get('/courses', [CourseController::class, 'index'])->name('public.courses.index');
Route::get('/courses/{course:slug}', [CourseController::class, 'show'])->name('public.courses.show');

// Admissions
Route::get('/admissions', [AdmissionController::class, 'create'])->name('public.admissions.create');
Route::post('/admissions', [AdmissionController::class, 'store'])->name('public.admissions.store');
Route::get('/admissions/thanks', [AdmissionController::class, 'thanks'])->name('public.admissions.thanks');

// News/Blog
Route::get('/news', [PostController::class, 'index'])->name('public.news.index');
Route::get('/news/{post:slug}', [PostController::class, 'show'])->name('public.news.show');

// Events
Route::get('/events', [EventController::class, 'index'])->name('public.events.index');
Route::get('/events/{event}', [EventController::class, 'show'])->name('public.events.show');

// Gallery
Route::get('/gallery', [GalleryController::class, 'index'])->name('public.gallery.index');
Route::get('/gallery/{gallery}', [GalleryController::class, 'show'])->name('public.gallery.show');

// Contact
Route::get('/contact', [ContactController::class, 'create'])->name('public.contact.create');
Route::post('/contact', [ContactController::class, 'store'])->name('public.contact.store');

// SEO Routes
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('public.sitemap');
Route::get('/robots.txt', function() {
    $content = "User-agent: *\n";
    $content .= "Allow: /\n";
    $content .= "Disallow: /admin/\n";
    $content .= "Disallow: /login\n";
    $content .= "Disallow: /register\n";
    $content .= "Disallow: /password/\n";
    $content .= "Disallow: /email/\n";
    $content .= "Disallow: /dashboard\n";
    $content .= "Disallow: /students/\n";
    $content .= "Disallow: /teachers/\n";
    $content .= "Disallow: /quran-progress/\n";
    $content .= "Disallow: /announcements/\n";
    $content .= "Disallow: /e-learning/\n";
    $content .= "Disallow: /substitutions/\n";
    $content .= "Disallow: /requests/\n";
    $content .= "Disallow: /absences/\n";
    $content .= "Disallow: /otp-login\n";
    $content .= "Disallow: /otp-verify\n";
    $content .= "Disallow: /otp-password/\n";
    $content .= "Disallow: /test\n";
    $content .= "Disallow: /lang-test\n\n";
    $content .= "Sitemap: " . url('/sitemap.xml') . "\n";
    
    return response($content, 200, ['Content-Type' => 'text/plain']);
})->name('public.robots');
