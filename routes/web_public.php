<?php

use App\Domains\Commerce\Http\Controllers\WalletController;
use App\Domains\Library\Http\Controllers\LibraryCheckoutController;
use App\Domains\Library\Http\Controllers\LibraryReaderController;
use App\Domains\Library\Http\Controllers\PublicLibraryController;
use App\Domains\Website\Http\Controllers\PublicSite\AdmissionController;
use App\Domains\Website\Http\Controllers\PublicSite\ContactController;
use App\Domains\Website\Http\Controllers\PublicSite\CourseController;
use App\Domains\Website\Http\Controllers\PublicSite\DailyContentController;
use App\Domains\Website\Http\Controllers\PublicSite\DailySubscriptionController;
use App\Domains\Website\Http\Controllers\PublicSite\DailyUnsubscribeController;
use App\Domains\Website\Http\Controllers\PublicSite\GalleryController;
use App\Domains\Website\Http\Controllers\PublicSite\HomeController;
use App\Domains\Website\Http\Controllers\PublicSite\InstructorProfileController;
use App\Domains\Website\Http\Controllers\PublicSite\PageController;
use App\Domains\Website\Http\Controllers\PublicSite\PrayerTimesController;
use App\Domains\Website\Http\Controllers\PublicSite\ResearchPostController;
use App\Domains\Website\Http\Controllers\PublicSite\SitemapController;
use Illuminate\Support\Facades\Route;

// Dynamic homepage - DB-driven content
Route::get('/', [HomeController::class, 'index'])->name('public.home');
Route::get('/en', function () {
    app()->setLocale('en');

    return app(HomeController::class)->index(request());
});
Route::get('/ar', function () {
    app()->setLocale('ar');

    return app(HomeController::class)->index(request());
});
Route::get('/dv', function () {
    app()->setLocale('dv');

    return app(HomeController::class)->index(request());
});

// Other routes
Route::get('about', [\App\Domains\Website\Http\Controllers\PublicSite\AboutController::class, 'index'])->name('public.about');
Route::get('careers', [\App\Domains\Website\Http\Controllers\PublicSite\CareersController::class, 'index'])->name('public.careers');
Route::get('courses', [CourseController::class, 'index'])->name('public.courses.index');
Route::get('courses/{course}', [CourseController::class, 'show'])->name('public.courses.show');
Route::post('courses/{course}/waitlist', [CourseController::class, 'waitlist'])->name('public.courses.waitlist')->middleware('throttle:10,1,course-waitlist');
Route::post('courses/{course}/syllabus', [CourseController::class, 'syllabus'])->name('public.courses.syllabus')->middleware('throttle:10,1,course-syllabus');
Route::post('funnel-events', [\App\Domains\Website\Http\Controllers\PublicSite\FunnelEventController::class, 'store'])->name('public.funnel.store')->middleware('throttle:60,1,funnel-events');
// Search
Route::get('search', [\App\Domains\Website\Http\Controllers\PublicSite\SearchController::class, 'index'])->name('public.search');

// Articles live in the Digital Library (RESEARCH_ARTICLES_PLAN R2): the
// index redirects there, and an article address is gone — none was ever published.
Route::get('articles', [\App\Domains\Website\Http\Controllers\PublicSite\PostController::class, 'articlesIndex'])->name('public.articles.index');
Route::get('articles/{slug}', [\App\Domains\Website\Http\Controllers\PublicSite\PostController::class, 'articleGone'])->name('public.articles.show');

// BOOKSHOP_PLAN B1b: the Akuru Online Bookshop. `shop/{vendor}` is last and
// refuses the words the shop itself uses, so a vendor can never be named
// "products", "c", "export", "cart", "checkout" or "slips".
Route::get('shop', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'index'])->name('public.shop.index');
Route::get('shop/deals', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'deals'])->name('public.shop.deals');
Route::get('shop/export', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'export'])->name('public.shop.export');
// B7: suggestions as you type.
Route::get('shop/suggest', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'suggest'])->name('public.shop.suggest')->middleware('throttle:120,1,shop-suggest');
Route::get('shop/products/{slug}', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'product'])->name('public.shop.product');
// §5lj: track an order without signing in — its number and phone, throttled against guessing.
Route::get('shop/track', [\App\Domains\Bookshop\Http\Controllers\MyOrdersController::class, 'track'])->name('public.shop.track')->middleware('throttle:10,1,shop-track');
Route::get('shop/compare', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'compare'])->name('public.shop.compare');
Route::post('shop/compare/{slug}', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'toggleCompare'])->name('public.shop.compare.toggle')->middleware('throttle:60,1,shop-compare');
Route::get('shop/brand/{slug}', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'brand'])->name('public.shop.brand');
Route::get('shop/c/{slug}', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'category'])->name('public.shop.category');
// B2: the cart is a guest's too (by session token), so it is public and
// throttled; checkout, its status page, slips and orders need a sign-in.
// Each limit carries its own prefix (the third argument): a plain
// `throttle:N,1` keys on the user alone, so every such route shares one
// counter, and ten cart adds in a minute used to get the checkout a 429
// (found by the B3 walk, 2026-09-26).
Route::get('shop/cart', [\App\Domains\Bookshop\Http\Controllers\ShopCartController::class, 'index'])->name('public.shop.cart');
Route::post('shop/cart', [\App\Domains\Bookshop\Http\Controllers\ShopCartController::class, 'add'])->name('public.shop.cart.add')->middleware('throttle:60,1,shop-cart');
// §5lc: a school's whole book list, in one tap.
Route::post('shop/book-lists/{vendor}/{list}', [\App\Domains\Bookshop\Http\Controllers\ShopCartController::class, 'addList'])->name('public.shop.book-list.add')->middleware('throttle:20,1,shop-book-list');
// §5lf: save for later, and back into the basket.
Route::post('shop/cart/{item}/save', [\App\Domains\Bookshop\Http\Controllers\ShopCartController::class, 'saveForLater'])->name('public.shop.cart.save')->middleware('throttle:60,1,shop-cart')->whereNumber('item');
Route::post('shop/cart/{item}/move', [\App\Domains\Bookshop\Http\Controllers\ShopCartController::class, 'moveToCart'])->name('public.shop.cart.move')->middleware('throttle:60,1,shop-cart')->whereNumber('item');
Route::post('shop/cart/{item}', [\App\Domains\Bookshop\Http\Controllers\ShopCartController::class, 'update'])->name('public.shop.cart.update')->middleware('throttle:60,1,shop-cart')->whereNumber('item');
Route::middleware('auth')->group(function () {
    Route::get('shop/checkout', [\App\Domains\Bookshop\Http\Controllers\CheckoutController::class, 'show'])->name('public.shop.checkout');
    Route::post('shop/checkout', [\App\Domains\Bookshop\Http\Controllers\CheckoutController::class, 'store'])->name('public.shop.checkout.store')->middleware('throttle:10,1,shop-checkout');
    Route::get('shop/checkout/{number}', [\App\Domains\Bookshop\Http\Controllers\CheckoutController::class, 'status'])->name('public.shop.checkout.status');
    Route::post('shop/checkout/{number}/slip', [\App\Domains\Bookshop\Http\Controllers\CheckoutController::class, 'uploadSlip'])->name('public.shop.checkout.slip')->middleware('throttle:10,1,shop-slip');
    Route::get('shop/slips/{slip}', [\App\Domains\Bookshop\Http\Controllers\CheckoutController::class, 'slip'])->name('public.shop.slip')->whereNumber('slip');
    Route::get('my-orders', [\App\Domains\Bookshop\Http\Controllers\MyOrdersController::class, 'index'])->name('public.shop.orders');
    Route::get('my-orders/export', [\App\Domains\Bookshop\Http\Controllers\MyOrdersController::class, 'export'])->name('public.shop.orders.export');
    Route::get('my-orders/{number}', [\App\Domains\Bookshop\Http\Controllers\MyOrdersController::class, 'show'])->name('public.shop.orders.show');
    // B3: the customer cancels before dispatch, asks for a return, writes to the shop.
    Route::post('my-orders/{number}/buy-again', [\App\Domains\Bookshop\Http\Controllers\MyOrdersController::class, 'buyAgain'])->name('public.shop.orders.buy-again')->middleware('throttle:20,1,shop-buy-again');
    Route::post('my-orders/{number}/cancel', [\App\Domains\Bookshop\Http\Controllers\MyOrdersController::class, 'cancel'])->name('public.shop.orders.cancel')->middleware('throttle:10,1,shop-cancel');
    Route::post('my-orders/{number}/returns', [\App\Domains\Bookshop\Http\Controllers\MyOrdersController::class, 'requestReturn'])->name('public.shop.orders.return')->middleware('throttle:10,1,shop-return');
    Route::post('my-orders/{number}/message', [\App\Domains\Bookshop\Http\Controllers\MyOrdersController::class, 'message'])->name('public.shop.orders.message')->middleware('throttle:20,1,shop-message');
    // B7: the wishlist, back-in-stock requests, reviews of what was received.
    Route::get('my-wishlist', [\App\Domains\Bookshop\Http\Controllers\ShopAccountController::class, 'wishlist'])->name('public.shop.wishlist');
    Route::get('my-wishlist/export', [\App\Domains\Bookshop\Http\Controllers\ShopAccountController::class, 'exportWishlist'])->name('public.shop.wishlist.export');
    Route::post('shop/wishlist/{slug}', [\App\Domains\Bookshop\Http\Controllers\ShopAccountController::class, 'toggleWishlist'])->name('public.shop.wishlist.toggle')->middleware('throttle:60,1,shop-wishlist');
    Route::post('shop/products/{slug}/notify', [\App\Domains\Bookshop\Http\Controllers\ShopAccountController::class, 'toggleStockAlert'])->name('public.shop.stock-alert')->middleware('throttle:30,1,shop-alert');
    Route::post('shop/reviews/{review}/helpful', [\App\Domains\Bookshop\Http\Controllers\ShopAccountController::class, 'helpful'])->name('public.shop.review.helpful')->middleware('throttle:30,1,shop-review-vote')->whereNumber('review');
    Route::post('shop/products/{slug}/questions', [\App\Domains\Bookshop\Http\Controllers\ShopAccountController::class, 'question'])->name('public.shop.question')->middleware('throttle:10,1,shop-question');
    Route::post('shop/products/{slug}/reviews', [\App\Domains\Bookshop\Http\Controllers\ShopAccountController::class, 'review'])->name('public.shop.review')->middleware('throttle:10,1,shop-review');
    // B9d: bulk quotes for schools — asked from the cart, accepted back into it.
    Route::post('shop/quotes', [\App\Domains\Bookshop\Http\Controllers\MyQuotesController::class, 'store'])->name('public.shop.quotes.store')->middleware('throttle:5,10,shop-quote');
    Route::get('my-quotes', [\App\Domains\Bookshop\Http\Controllers\MyQuotesController::class, 'index'])->name('public.shop.quotes');
    Route::get('my-quotes/export', [\App\Domains\Bookshop\Http\Controllers\MyQuotesController::class, 'export'])->name('public.shop.quotes.export');
    Route::get('my-quotes/{number}', [\App\Domains\Bookshop\Http\Controllers\MyQuotesController::class, 'show'])->name('public.shop.quotes.show');
    Route::post('my-quotes/{number}/accept', [\App\Domains\Bookshop\Http\Controllers\MyQuotesController::class, 'accept'])->name('public.shop.quotes.accept')->middleware('throttle:20,1,shop-quote-decide');
    Route::post('my-quotes/{number}/withdraw', [\App\Domains\Bookshop\Http\Controllers\MyQuotesController::class, 'withdraw'])->name('public.shop.quotes.withdraw')->middleware('throttle:20,1,shop-quote-decide');
});
// B9c: a shop's newsletter — sign up on its page; the unsubscribe page its mailings link to.
Route::get('shop/newsletter/unsubscribe/{token}', [\App\Domains\Bookshop\Http\Controllers\NewsletterController::class, 'show'])->name('public.shop.newsletter.unsubscribe')->where('token', '[A-Za-z0-9]{48}');
Route::post('shop/newsletter/unsubscribe/{token}', [\App\Domains\Bookshop\Http\Controllers\NewsletterController::class, 'unsubscribe'])->name('public.shop.newsletter.unsubscribe.confirm')->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:20,1,shop-newsletter-leave');
Route::post('shop/{vendor}/newsletter', [\App\Domains\Bookshop\Http\Controllers\NewsletterController::class, 'subscribe'])->name('public.shop.newsletter.subscribe')->where('vendor', '[a-z0-9-]+')->middleware('throttle:10,1,shop-newsletter');
Route::get('shop/{vendor}', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'vendor'])->name('public.shop.vendor')
    ->where('vendor', '(?!(products|c|export|cart|checkout|slips|suggest|wishlist|newsletter)$)[a-z0-9-]+');
// B5: a vendor's own pages and collections under its storefront (plan §6.4, §5).
Route::get('shop/{vendor}/p/{page}', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'vendorPage'])->name('public.shop.vendor.page')
    ->where('vendor', '(?!(products|c|export|cart|checkout|slips|suggest|wishlist)$)[a-z0-9-]+')->where('page', '[a-z0-9-]+');
Route::get('shop/{vendor}/{collection}', [\App\Domains\Bookshop\Http\Controllers\ShopController::class, 'vendorCollection'])->name('public.shop.vendor.collection')
    ->where('vendor', '(?!(products|c|export|cart|checkout|slips|suggest|wishlist)$)[a-z0-9-]+')->where('collection', '(?!p$)[a-z0-9-]+');

Route::get('library/export', [PublicLibraryController::class, 'export'])->name('public.library.export');
Route::get('library', [PublicLibraryController::class, 'index'])->name('public.library.index');
Route::get('library/authors/{slug}', [PublicLibraryController::class, 'author'])->name('public.library.author');
// B4 (§8.5): the offers running now.
Route::get('library/promotions', [PublicLibraryController::class, 'promotions'])->name('public.library.promotions');
Route::get('my-library', [LibraryReaderController::class, 'myLibrary'])->name('public.library.my');
Route::get('my-wallet', [WalletController::class, 'show'])->name('public.wallet');
Route::post('my-wallet/redeem', [WalletController::class, 'redeem'])->name('public.wallet.redeem')->middleware('throttle:10,1,wallet-redeem');
// L4 §15.3: buy a gift card. BML only; no discount, no wallet (§15.4).
Route::get('gift-cards', [\App\Domains\Commerce\Http\Controllers\GiftCardPurchaseController::class, 'index'])->name('public.gift-cards.index');
Route::post('gift-cards', [\App\Domains\Commerce\Http\Controllers\GiftCardPurchaseController::class, 'purchase'])->name('public.gift-cards.purchase')->middleware('throttle:10,1,gift-card-buy');
Route::get('gift-cards/return', [\App\Domains\Commerce\Http\Controllers\GiftCardPurchaseController::class, 'paymentReturn'])->name('public.gift-cards.return');
Route::get('library/{slug}/download', [PublicLibraryController::class, 'download'])->name('public.library.download')->middleware('throttle:20,1,library-download');
Route::get('library/{slug}/read', [LibraryReaderController::class, 'read'])->name('public.library.read');
Route::post('library/{slug}/checkout', [LibraryCheckoutController::class, 'checkout'])->name('public.library.checkout')->middleware('throttle:10,1,library-checkout');
Route::get('library/{slug}/payment-return', [LibraryCheckoutController::class, 'paymentReturn'])->name('public.library.payment-return');
Route::post('library/{slug}/progress', [LibraryReaderController::class, 'progress'])->name('public.library.progress')->middleware('throttle:60,1,library-progress');
Route::post('library/{slug}/bookmark', [LibraryReaderController::class, 'bookmark'])->name('public.library.bookmark')->middleware('throttle:30,1,library-bookmark');
Route::post('library/{slug}/note', [LibraryReaderController::class, 'note'])->name('public.library.note')->middleware('throttle:30,1,library-note');
Route::get('library/{slug}', [PublicLibraryController::class, 'show'])->name('public.library.show');

// Research lives in the Digital Library (R2): these three redirect there.
Route::get('research/export', [ResearchPostController::class, 'export'])->name('public.research.export');
Route::get('research', [ResearchPostController::class, 'index'])->name('public.research.index');
Route::get('research/{slug}', [ResearchPostController::class, 'show'])->name('public.research.show');
Route::get('instructors/{slug}', [InstructorProfileController::class, 'show'])->name('public.instructors.show');
Route::get('prayer-times', [PrayerTimesController::class, 'index'])->name('public.prayer-times');
Route::post('prayer-times/sms-opt-out', [PrayerTimesController::class, 'smsOptOut'])
    ->middleware('throttle:10,1,prayer-sms-opt-out')
    ->name('public.prayer-times.sms-opt-out');

// Calendar .ics download for individual event
Route::get('events/{event}/calendar.ics', [\App\Domains\Website\Http\Controllers\PublicSite\EventController::class, 'downloadCalendar'])->name('public.events.calendar');
Route::post('events/{event}/register', [\App\Domains\Website\Http\Controllers\PublicSite\EventController::class, 'register'])->name('public.events.register')->middleware('throttle:20,1,event-register');

Route::get('news', [\App\Domains\Website\Http\Controllers\PublicSite\PostController::class, 'newsIndex'])->name('public.news.index');
Route::get('news/{post:slug}', [\App\Domains\Website\Http\Controllers\PublicSite\PostController::class, 'show'])->name('public.news.show');
// The events list and page used to catch every exception and print its
// message as a 500 — a missing event read "Event detail error: No query
// results for model…" instead of a 404. Errors now take the normal path.
Route::get('events', function () {
    $events = \App\Domains\Website\Models\Event::published()->public()->with('registrations')->paginate(12);
    $holidays = app(\App\Domains\Academics\Actions\ListCalendarHolidaysAction::class)->execute();

    return view('public.events.index', compact('events', 'holidays'));
})->name('public.events.index');
// By slug or id (Event::resolveRouteBinding); a draft or private event is a 404.
Route::get('events/{event}', [\App\Domains\Website\Http\Controllers\PublicSite\EventController::class, 'show'])->name('public.events.show');
Route::get('achievements', [\App\Domains\Website\Http\Controllers\PublicSite\AchievementController::class, 'index'])->name('public.achievements');
Route::get('gallery', [GalleryController::class, 'index'])->name('public.gallery.index');
Route::get('gallery/{gallery}', [GalleryController::class, 'show'])->name('public.gallery.show');
Route::get('daily/subscribe', [DailySubscriptionController::class, 'index'])
    ->middleware('auth')
    ->name('public.daily.subscribe');
Route::post('daily/subscribe', [DailySubscriptionController::class, 'store'])
    ->middleware('auth')
    ->name('public.daily.subscribe.store');
Route::post('daily/subscribe/{subscription}/pause', [DailySubscriptionController::class, 'pause'])
    ->middleware('auth')
    ->name('public.daily.subscribe.pause')
    ->whereNumber('subscription');
Route::post('daily/subscribe/{subscription}/resume', [DailySubscriptionController::class, 'resume'])
    ->middleware('auth')
    ->name('public.daily.subscribe.resume')
    ->whereNumber('subscription');
Route::get('daily/unsubscribe/{token}', [DailyUnsubscribeController::class, 'show'])
    ->name('public.daily.unsubscribe');
Route::post('daily/sms-opt-out', [DailyUnsubscribeController::class, 'smsOptOut'])
    ->middleware('throttle:10,1,daily-sms-opt-out')
    ->name('public.daily.sms-opt-out');
Route::get('daily/{type}', [DailyContentController::class, 'index'])
    ->name('public.daily.index')
    ->where('type', 'ayah|hadith|saying|reminder');
Route::get('daily/{type}/{date}/card.png', [DailyContentController::class, 'card'])
    ->name('public.daily.card')
    ->where(['type' => 'ayah|hadith|saying|reminder', 'date' => '\d{4}-\d{2}-\d{2}']);
Route::get('daily/{type}/{date}', [DailyContentController::class, 'show'])
    ->name('public.daily.show')
    ->where(['type' => 'ayah|hadith|saying|reminder', 'date' => '\d{4}-\d{2}-\d{2}']);
// Public course registration flow (guest + auth)
Route::get('courses/{course}/checkout', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'checkout'])
    ->name('courses.checkout.show');
Route::post('courses/{course}/checkout/login', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'checkoutLogin'])
    ->name('courses.checkout.login')->middleware('throttle:10,1,register-login');
// Legacy register route kept for backward compatibility
Route::get('courses/{course}/register', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'show'])
    ->name('courses.register.show');
Route::post('courses/register/start', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'start'])
    ->name('courses.register.start')->middleware('throttle:10,1,register-start');
Route::get('courses/register/otp', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'otpForm'])
    ->name('courses.register.otp');
Route::post('courses/register/verify', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'verify'])
    ->name('courses.register.verify')->middleware('throttle:10,1,register-verify');
Route::post('courses/register/otp/resend-new', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'resendNewRegistrationOtp'])
    ->name('courses.register.otp.resend-new')->middleware('throttle:5,1,register-otp-resend');
Route::get('courses/register/set-password', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'passwordForm'])
    ->name('courses.register.set-password');
Route::post('courses/register/set-password', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'setPassword'])
    ->name('courses.register.set-password.store');
Route::get('courses/register/continue', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'continueForm'])
    ->name('courses.register.continue');
Route::post('courses/register/enroll', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'enroll'])
    ->name('courses.register.enroll')->middleware('throttle:10,1,register-enroll');
// Graceful GET fallback — browser history / stale link navigation
Route::get('courses/register/enroll', function () {
    if (session('enroll_pending_course_ids')) {
        return redirect()->route('courses.register.enroll.otp');
    }
    if (session('pending_selected_course_ids') || session('pending_course_id')) {
        return redirect()->route('courses.register.continue');
    }

    return redirect()->route('public.courses.index')
        ->with('info', 'Please select a course to start enrollment.');
});
Route::get('courses/register/enroll/confirm', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'enrollOtpForm'])
    ->name('courses.register.enroll.otp');
Route::post('courses/register/enroll/confirm', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'enrollConfirm'])
    ->name('courses.register.enroll.confirm')->middleware('throttle:10,1,register-enroll-confirm');
Route::post('courses/register/enroll/resend', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'enrollResendOtp'])
    ->name('courses.register.enroll.resend')->middleware('throttle:5,1,register-enroll-resend');
Route::get('courses/register/complete', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'complete'])
    ->name('courses.register.complete');
Route::get('courses/register/resume', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'resume'])
    ->name('courses.register.resume');
Route::get('courses/register/payment/retry', [\App\Domains\Admissions\Http\Controllers\CourseRegistrationController::class, 'retryPayment'])
    ->name('courses.register.payment.retry');

// Checkout (compliance checkbox required before payment; auth required)
Route::get('checkout/course/{course}', [\App\Domains\Admissions\Http\Controllers\CheckoutController::class, 'show'])
    ->name('checkout.course.show')->middleware('auth');
Route::post('payments/course/{course}/start', [\App\Domains\Admissions\Http\Controllers\CheckoutController::class, 'start'])
    ->name('payments.course.start')->middleware('auth');

// Payment routes
Route::get('payments/return/{payment}', [\App\Domains\Finance\Http\Controllers\PaymentController::class, 'returnByPayment'])
    ->name('payments.return');
Route::get('payments/ref/{merchant_reference}/status', [\App\Domains\Finance\Http\Controllers\PaymentController::class, 'status'])
    ->name('payments.status');
Route::post('payments/bml/initiate', [\App\Domains\Finance\Http\Controllers\PaymentController::class, 'initiate'])
    ->name('payments.bml.initiate');

// The old course portal (docs/SIGN_IN_PLAN.md ID2b). Its five Blade pages,
// in the website's layout, are retired into the shell: its dashboard is the
// person's workspace home, its enrolments and payments are My enrolments, its
// certificates are on My learning, its profile is the profile. The addresses
// stay, as redirects, for bookmarks, old emails and the website's header.
Route::middleware('auth')->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', fn () => redirect()->route('dashboard'))->name('dashboard');
    Route::get('/enrollments', fn () => redirect()->route('my.enrollments'))->name('enrollments');
    Route::get('/payments', fn () => redirect()->route('my.enrollments'))->name('payments');
    Route::get('/certificates', fn () => redirect()->route('learn.dashboard'))->name('certificates');
    Route::get('/profile', fn () => redirect()->route('profile.edit'))->name('profile');
});

// Account management (auth required)
Route::middleware('auth')->group(function () {
    Route::get('account/set-password', [\App\Domains\Identity\Http\Controllers\AccountController::class, 'setPasswordForm'])
        ->name('account.set-password');
    Route::post('account/set-password', [\App\Domains\Identity\Http\Controllers\AccountController::class, 'setPassword'])
        ->name('account.set-password.store');

    Route::get('payments/{payment}/receipt', [\App\Domains\Finance\Http\Controllers\PaymentReceiptController::class, 'show'])
        ->name('payment.receipt');
});

Route::get('admissions', [AdmissionController::class, 'create'])->name('public.admissions.create');
Route::post('admissions', [AdmissionController::class, 'store'])->name('public.admissions.store');
Route::get('admissions/thanks', [AdmissionController::class, 'thanks'])->name('public.admissions.thanks');
// /apply is a friendly alias for the admissions form (shows a cleaner marketing-focused view)
Route::get('apply', [AdmissionController::class, 'applyPage'])->name('public.apply');
Route::post('apply', [AdmissionController::class, 'store'])->name('public.apply.store');
Route::get('contact', [ContactController::class, 'create'])->name('public.contact.create');
Route::post('contact', [ContactController::class, 'store'])->name('public.contact.store');
Route::get('terms', [\App\Domains\Website\Http\Controllers\PolicyViewController::class, 'terms'])->name('public.terms');
Route::get('privacy', [\App\Domains\Website\Http\Controllers\PolicyViewController::class, 'privacy'])->name('public.privacy');
Route::get('refunds', [\App\Domains\Website\Http\Controllers\PolicyViewController::class, 'refunds'])->name('public.refunds');
Route::get('services', [\App\Domains\Website\Http\Controllers\PolicyViewController::class, 'services'])->name('public.services');
Route::get('page/{slug}', [PageController::class, 'show'])->name('public.page.show');

// SEO routes
Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('public.sitemap');
Route::get('robots.txt', function () {
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
    $content .= 'Sitemap: '.url('/sitemap.xml')."\n";

    return response($content, 200, ['Content-Type' => 'text/plain']);
})->name('public.robots');
