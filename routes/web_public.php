<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PublicSite\{
    HomeController,
    CourseController,
    AdmissionController,
    GalleryController,
    PageController,
    ContactController,
    SitemapController
};

// Dynamic homepage - DB-driven content
Route::get("/", [HomeController::class, "index"])->name("public.home");
Route::get("/en", function () {
    app()->setLocale("en");
    return app(HomeController::class)->index(request());
});
Route::get("/ar", function () {
    app()->setLocale("ar");
    return app(HomeController::class)->index(request());
});
Route::get("/dv", function () {
    app()->setLocale("dv");
    return app(HomeController::class)->index(request());
});

// Other routes
Route::get("about", fn() => app(PageController::class)->show('about'))->name("public.about");
Route::get("courses", [CourseController::class, "index"])->name("public.courses.index");
Route::get("courses/{course}", [CourseController::class, "show"])->name("public.courses.show");
Route::get("news", function() {
    try {
        $posts = \App\Models\Post::published()->public()->with('category')->paginate(12);
        return view('public.news.index', compact('posts'));
    } catch (\Exception $e) {
        return response('News error: ' . $e->getMessage(), 500);
    }
})->name("public.news.index");
Route::get("news/{post}", function($identifier) {
    try {
        $post = \App\Models\Post::published()->public()->with('category')
            ->where(function($q) use ($identifier) {
                $q->where('id', $identifier)->orWhere('slug', $identifier);
            })->firstOrFail();
        return view('public.news.show', compact('post'));
    } catch (\Exception $e) {
        return response('News detail error: ' . $e->getMessage(), 500);
    }
})->name("public.news.show");
Route::get("events", function() {
    try {
        $events = \App\Models\Event::published()->public()->with('registrations')->paginate(12);
        return view('public.events.index', compact('events'));
    } catch (\Exception $e) {
        return response('Events error: ' . $e->getMessage(), 500);
    }
})->name("public.events.index");
Route::get("events/{event}", function($id) {
    try {
        $event = \App\Models\Event::published()->public()->with('registrations')->findOrFail($id);
        return view('public.events.show', compact('event'));
    } catch (\Exception $e) {
        return response('Event detail error: ' . $e->getMessage(), 500);
    }
})->name("public.events.show");
Route::get("gallery", [GalleryController::class, "index"])->name("public.gallery.index");
Route::get("gallery/{gallery}", [GalleryController::class, "show"])->name("public.gallery.show");
// Public course registration flow (guest + auth)
Route::get("courses/{course}/checkout", [\App\Http\Controllers\CourseRegistrationController::class, "checkout"])
    ->name("courses.checkout.show");
Route::post("courses/{course}/checkout/login", [\App\Http\Controllers\CourseRegistrationController::class, "checkoutLogin"])
    ->name("courses.checkout.login")->middleware('throttle:10,1');
// Legacy register route kept for backward compatibility
Route::get("courses/{course}/register", [\App\Http\Controllers\CourseRegistrationController::class, "show"])
    ->name("courses.register.show");
Route::post("courses/register/start", [\App\Http\Controllers\CourseRegistrationController::class, "start"])
    ->name("courses.register.start")->middleware('throttle:10,1');
Route::get("courses/register/otp", [\App\Http\Controllers\CourseRegistrationController::class, "otpForm"])
    ->name("courses.register.otp");
Route::post("courses/register/verify", [\App\Http\Controllers\CourseRegistrationController::class, "verify"])
    ->name("courses.register.verify")->middleware('throttle:10,1');
Route::get("courses/register/set-password", [\App\Http\Controllers\CourseRegistrationController::class, "passwordForm"])
    ->name("courses.register.set-password");
Route::post("courses/register/set-password", [\App\Http\Controllers\CourseRegistrationController::class, "setPassword"])
    ->name("courses.register.set-password.store");
Route::get("courses/register/continue", [\App\Http\Controllers\CourseRegistrationController::class, "continueForm"])
    ->name("courses.register.continue");
Route::post("courses/register/enroll", [\App\Http\Controllers\CourseRegistrationController::class, "enroll"])
    ->name("courses.register.enroll");
Route::get("courses/register/complete", [\App\Http\Controllers\CourseRegistrationController::class, "complete"])
    ->name("courses.register.complete");
Route::get("courses/register/resume", [\App\Http\Controllers\CourseRegistrationController::class, "resume"])
    ->name("courses.register.resume");
Route::get("courses/register/payment/retry", [\App\Http\Controllers\CourseRegistrationController::class, "retryPayment"])
    ->name("courses.register.payment.retry");

// Checkout (compliance checkbox required before payment; auth required)
Route::get("checkout/course/{course}", [\App\Http\Controllers\CheckoutController::class, "show"])
    ->name("checkout.course.show")->middleware('auth');
Route::post("payments/course/{course}/start", [\App\Http\Controllers\CheckoutController::class, "start"])
    ->name("payments.course.start")->middleware('auth');

// Payment routes
Route::get("payments/return/{payment}", [\App\Http\Controllers\PaymentController::class, "returnByPayment"])
    ->name("payments.return");
Route::get("payments/ref/{merchant_reference}/status", [\App\Http\Controllers\PaymentController::class, "status"])
    ->name("payments.status");
Route::post("payments/bml/initiate", [\App\Http\Controllers\PaymentController::class, "initiate"])
    ->name("payments.bml.initiate");

// Account management (auth required)
Route::middleware('auth')->group(function () {
    Route::get('account/set-password', [\App\Http\Controllers\AccountController::class, 'setPasswordForm'])
        ->name('account.set-password');
    Route::post('account/set-password', [\App\Http\Controllers\AccountController::class, 'setPassword'])
        ->name('account.set-password.store');
});

Route::get("admissions", [AdmissionController::class, "create"])->name("public.admissions.create");
Route::post("admissions", [AdmissionController::class, "store"])->name("public.admissions.store");
Route::get("admissions/thanks", [AdmissionController::class, "thanks"])->name("public.admissions.thanks");
Route::get("contact", [ContactController::class, "create"])->name("public.contact.create");
Route::post("contact", [ContactController::class, "store"])->name("public.contact.store");
Route::get("terms", [\App\Http\Controllers\PolicyViewController::class, "terms"])->name("public.terms");
Route::get("privacy", [\App\Http\Controllers\PolicyViewController::class, "privacy"])->name("public.privacy");
Route::get("refunds", [\App\Http\Controllers\PolicyViewController::class, "refunds"])->name("public.refunds");
Route::get("services", [\App\Http\Controllers\PolicyViewController::class, "services"])->name("public.services");
Route::get("page/{slug}", [PageController::class, "show"])->name("public.page.show");

// SEO routes
Route::get("sitemap.xml", [SitemapController::class, "index"])->name("public.sitemap");
Route::get("robots.txt", function() {
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
    $content .= "Sitemap: " . url("/sitemap.xml") . "\n";
    
    return response($content, 200, ["Content-Type" => "text/plain"]);
})->name("public.robots");