# Architecture Audit — Akuru Institute

> **Conducted:** February 2026
> **Purpose:** Full pre-refactor audit. Source of truth before any modular monolith work begins.
> **Reference:** See `MODULAR_MONOLITH_GUIDE.md` for the implementation plan that follows this audit.

---

## A. Current Architecture

### App Structure

```
app/
  Exceptions/           ← none (Laravel 12 — handled in bootstrap/app.php)
  Http/
    Controllers/        ← 50+ controllers, flat namespace with sub-dirs:
      Admin/            ← EnrollmentController, InstructorController,
                           SettingsController, AdminUserController,
                           PublicSite/{CourseController, PageController}
      Api/              ← SmsApiController
      Auth/             ← 15 auth controllers (Breeze + legacy UI stubs)
      ELearning/        ← QuizController
      Portal/           ← PortalController
      PublicSite/       ← AboutController, AdmissionController,
                           ContactController, CourseController,
                           EventController, GalleryController,
                           HomeController, PageController,
                           PostController, SearchController,
                           SitemapController
      Substitutions/    ← SubstitutionRequestController,
                           TeacherAbsenceController
    Middleware/         ← SetLocale, SecurityHeaders,
                           ConvertEnroll403ToRedirect,
                           EnsureVerifiedContact, TrackUserActivity
    Requests/
      Auth/             ← 5 form requests
      Registration/     ← 5 form requests
      (root)            ← ProfileUpdateRequest (no authorize())
  Models/               ← 66 models (LMS + public-site + payment + content)
  Providers/            ← AppServiceProvider only
  Services/
    Payment/            ← PaymentService, BmlPaymentProvider,
                           PaymentProviderInterface (DTO classes)
    Enrollment/         ← EnrollmentService, EnrollmentResult
    (root)              ← AccountResolverService, AnalyticsService,
                           BmlConnectService, ContactNormalizer,
                           IslamicCalendarService, NotificationService,
                           OtpService, SmsGatewayService, WebPImageService
```

No `app/Actions/`, `app/Domains/`, or `app/Support/` directories exist.

---

### Route Organization

| File | Lines | Scope |
|------|-------|-------|
| `routes/web.php` | 31 | Entry point: bare BML webhook/return routes + localized group |
| `routes/web_public.php` | 190 | Public site, registration wizard, payments, portal, admissions |
| `routes/web_localized.php` | 134 | Dashboard, admin, academic, profile (requires `auth.php`) |
| `routes/auth.php` | 105 | Breeze auth routes |
| `routes/api.php` | 38 | SMS v2 API + notification endpoints |
| `routes/console.php` | 18 | Scheduled tasks |

**Two closure routes** exist in `web_public.php` for events (index + show) — the `PublicSite\EventController` already has `index()` and `show()` methods but is not wired.

**robots.txt** is served via a closure route in `web_public.php`.

---

### Controller Organization

**50+ controllers total.** Key observations:

- `CourseRegistrationController` — **1200+ lines, 17 public methods** — the largest and most complex controller, covering an 8-step multi-page wizard.
- Several root-level controllers exist but are **not wired to any route**: `AssignmentController`, `ContactMessageController`, `FaqController`, `HeroBannerController`, `HomeController` (root-level, duplicate of `PublicSite\HomeController`), `PostController` (root-level), `PageController` (root-level), `TestimonialController`.
- **15 legacy auth controllers** (`LoginController`, `RegisterController`, etc.) only define `__construct` using Laravel UI traits — superseded by Breeze controllers but left in place.
- `PaymentController` injects both `PaymentService` (abstracted) and `BmlConnectService` (concrete) — bypassing the `PaymentProviderInterface` abstraction in some methods.

---

### Service Organization

| Service | Responsibility |
|---------|---------------|
| `PaymentService` | Orchestrates payment lifecycle: create, initiate, finalize, webhook |
| `BmlPaymentProvider` | Concrete BML HTTP client (canonical path via interface) |
| `BmlConnectService` | **Legacy** BML HTTP client (used directly by `PaymentController`) |
| `EnrollmentService` | Enrollment creation (adult/parent flows + deferred post-payment) |
| `OtpService` | OTP send/verify (DB-backed for existing users, cache-backed for new) |
| `AccountResolverService` | Resolves or creates user by contact (phone/email) |
| `NotificationService` | Multi-channel notification dispatch (FCM, DB, email, SMS) |
| `SmsGatewayService` | SMS sending via Dhiraagu or fallback HTTP gateway |
| `ContactNormalizer` | Phone/email normalization |
| `AnalyticsService` | Dashboard analytics aggregation |
| `IslamicCalendarService` | Hijri calendar conversion (static methods) |
| `WebPImageService` | WebP image path resolution |

**Circular DI:** `EnrollmentService` takes `PaymentService` in its constructor; `PaymentService` resolves `EnrollmentService` lazily via `app(EnrollmentService::class)` to break the cycle. This is noted in comments.

---

### Models

**66 models across two distinct domains that share one database:**

**LMS Domain** (school operations):
`School`, `Student`, `Teacher`, `ClassRoom`, `Subject`, `Period`, `Timetable`, `Grade`, `Attendance`, `Assignment`, `AssignmentSubmission`, `QuranProgress`, `RecitationPractice`, `TajweedFeedback`, `Surah`, `LessonLog`, `CoursePlan`, `PlanTopic`, `AbsenceNote`, `TeacherAbsence`, `SubstitutionRequest`, `SubstitutionAssignment`, `ParentGuardian`, `AcademicYear`

**Public Platform Domain** (courses, enrollment, payment, content):
`User`, `UserContact`, `Otp`, `Device`, `UserActivity`, `RegistrationStudent`, `CourseEnrollment`, `Course`, `CourseCategory`, `Instructor`, `Payment`, `PaymentItem`, `FeeItem`, `Invoice`, `InvoiceLine`, `AdmissionApplication`, `Post`, `PostCategory`, `Event`, `EventRegistration`, `HeroBanner`, `GalleryAlbum`, `GalleryItem`, `MediaGallery`, `MediaItem`, `Testimonial`, `Faq`, `Page`, `ContactMessage`, `ContactInquiry`, `InquiryType`, `Setting`, `Announcement`, `Notification`, `NotificationTemplate`, `UserNotification`, `RegistrationFlow`, `DashboardAnalytics`, `SystemMetric`, `Report`, `Message`

**Key note:** `Student` (LMS) and `RegistrationStudent` (public enrollment) are separate models. Do NOT conflate them.

**`RegistrationStudent.national_id` and `.passport` are encrypted** — cannot be queried at the DB level. Duplicate detection is done by loading all students and decrypting in PHP (O(n) scan). This is a known scaling risk.

**`Payment` is polymorphic** (`payable_type`/`payable_id`) but also has direct `course_id`/`student_id` fields — partial redundancy.

---

### Request Validation

| Location | Classes | Notes |
|----------|---------|-------|
| `App\Http\Requests\Auth\` | 5 classes | All have `authorize()` + `rules()` |
| `App\Http\Requests\Registration\` | 5 classes | `EnrollAdultRequest` has custom `withValidator()` |
| `App\Http\Requests\ProfileUpdateRequest` | 1 class | No `authorize()` defined |

Most public form validation is done inline in controllers (admissions, contact) — no Form Requests for those flows.

---

### Middleware

| Middleware | Alias | Applied Where |
|-----------|-------|---------------|
| `SetLocale` | — | Global web |
| `SecurityHeaders` | — | Global web |
| `ConvertEnroll403ToRedirect` | `convert_enroll_403` | Global + alias |
| `EnsureVerifiedContact` | `verified_contact` | On-demand |
| `TrackUserActivity` | `trackActivity` | Dashboard routes |
| `LocaleSessionRedirect` (mcamara) | `localeSessionRedirect` | Localized group |
| `LaravelLocalizationRedirectFilter` (mcamara) | `localizationRedirect` | Localized group |
| `LaravelLocalizationViewPath` (mcamara) | `localeViewPath` | Localized group |
| `RoleMiddleware` (spatie) | `role` | Admin/role-specific routes |
| `PermissionMiddleware` (spatie) | `permission` | On-demand |

**CSRF exempt:** `payments/bml/callback`, `webhooks/bml`

---

### Frontend Stack

| Layer | Tool/Library | Notes |
|-------|-------------|-------|
| Build | Vite 7 + laravel-vite-plugin 2 | Standard |
| CSS | Tailwind CSS 3 + @tailwindcss/forms + @tailwindcss/typography | Typography installed but not in plugins array |
| JS | Alpine.js 3 | Used for all interactive components |
| Fonts | Figtree (bunny.net), Amiri (Google Fonts), Faruma (self-hosted, Thaana) | |
| HTTP | Axios 1.11 | CSRF token pre-configured |
| Dates | Day.js 1.11 | |
| Calendar | FullCalendar 6 | Imported but only used in specific dashboard views |
| i18n | mcamara/laravel-localization (3 locales) + Google Translate cookie | Google Translate handles runtime language switching |

**No React, Vue, Inertia, or Livewire.**

**Brand tokens defined in `tailwind.config.js`:**
- `brandMaroon` (primary: `#7C2D37`)
- `brandGold` (accent: `#C9A227`)
- `brandBeige` (background: `#F9F4EE`)
- `brandGray` (neutral)

**Critical inconsistency:** `home.blade.php` uses inline `style=` with hardcoded hex values (`#7C2D37`, `#C9A227`) instead of the `brandMaroon-*`/`brandGold-*` utility classes. The CSS layer defines `.btn-primary`/`.btn-secondary` component classes but they are not used in the homepage. Inline styles and Tailwind utilities coexist throughout public views.

---

### Tests

| File | What It Covers |
|------|---------------|
| `Feature/Auth/AuthenticationTest.php` | Login, logout, wrong password |
| `Feature/Auth/EmailVerificationTest.php` | Verification notice, signed URL, invalid hash |
| `Feature/Auth/PasswordConfirmationTest.php` | Confirm screen, correct/wrong password |
| `Feature/Auth/PasswordResetTest.php` | Reset screen, OTP send, verify form, reset form |
| `Feature/Auth/PasswordUpdateTest.php` | Password update, rejection |
| `Feature/Auth/RegistrationTest.php` | Registration screen, new user flow |
| `Feature/BmlWebhookTest.php` | Webhook confirms payment + enrollment; idempotency |
| `Feature/CheckoutPaymentTest.php` | Terms required; payment created; BML redirect |
| `Feature/CourseRegistrationTest.php` | Start creates user; OTP verify; adult enroll; under-18 blocked; duplicate prevention |
| `Feature/PaymentCallbackTest.php` | Callback confirms payment; idempotency |
| `Feature/ProfileTest.php` | Profile display, update, delete, wrong password |
| `Feature/RegistrationFlowTest.php` | 7 critical flow tests (null safety, OTP reuse, session, return URL, HMAC, idempotency) |
| `Feature/ExampleTest.php` | Homepage returns HTTP 200 |
| `Unit/ExampleTest.php` | Trivial sanity (`assertTrue(true)`) |

**Good coverage** of all critical payment and enrollment flows. No unit tests for domain services (OtpService, EnrollmentService, BmlPaymentProvider).

---

### Docs

| File | Status |
|------|--------|
| `docs/PROJECT_OVERVIEW.md` | Good |
| `docs/TECH_STACK.md` | Good |
| `docs/DATABASE_SCHEMA.md` | Good |
| `docs/AUTHENTICATION_GUIDE.md` | Good |
| `docs/USER_ROLES_GUIDE.md` | Good |
| `docs/COURSE_REGISTRATION_OVERVIEW.md` | Good |
| `docs/Payments-BML.md` | Good |
| `docs/MODULAR_MONOLITH_GUIDE.md` | Good (implementation plan) |
| `docs/TRANSLATION_MANAGEMENT.md` | Good |
| `docs/NEXT_STEPS.md` | Good |
| `README.md` | **Default Laravel README — not updated for this project** |

---

### Deployment Assumptions

- **Server:** LiteSpeed (inferred from `lscache` directory on server)
- **Subdomain:** `test.akuru.edu.mv` → `/home/akuruedu/test.akuru.edu.mv`
- **PHP:** Laravel 12 (PHP 8.2+)
- **Queue:** Database driver (`QUEUE_CONNECTION=database`)
- **Cache:** File or database (default)
- **Storage:** Local disk (S3 config present but CDN optional)
- **SMS:** Dhiraagu gateway (Maldives) + fallback HTTP gateway
- **Payment:** BML Connect (production credentials separate from test)
- **Scheduled tasks:** `payments:reconcile` (every 10 min), `akuru:prune-expired` (hourly), `akuru:scheduler-heartbeat` (every minute)

---

## B. What Is Already Good

1. **Payment flow is functionally complete and tested.** BML webhook + return URL handlers are idempotent (DB `lockForUpdate`). Two tests confirm idempotency explicitly. The `enrollment_pending_payload` deferred enrollment pattern is clean and correct.

2. **Critical flow test coverage is strong.** `RegistrationFlowTest`, `BmlWebhookTest`, `CheckoutPaymentTest`, and `PaymentCallbackTest` cover the most financially risky flows with realistic assertions.

3. **Payment webhook URLs are correctly isolated** from the locale-prefix routing group in `routes/web.php`. BML redirect URLs will not accidentally get locale-prefixed 302s.

4. **OTP service is well-designed.** Two separate paths (DB-backed for existing users, cache-backed for new registrations) with rate limiting, cooldowns, expiry, and attempt tracking. OTP codes are hashed before storage (`Hash::make`).

5. **Service layer exists and is reasonably well-separated.** `PaymentService`, `EnrollmentService`, `OtpService`, and `AccountResolverService` have clear responsibilities and reasonable interfaces.

6. **Localization support is real.** Three locales (en/ar/dv), translation files are comprehensive (~330 public keys), RTL support is in the CSS layer. Google Translate handles runtime switching without page reloads.

7. **Brand tokens are defined properly in Tailwind config** — `brandMaroon`, `brandGold`, `brandBeige` — even though they are not consistently applied everywhere.

8. **Sensitive data is encrypted at rest.** `RegistrationStudent.national_id` and `.passport` use Laravel's `encrypted` cast. Migration `2026_02_21_000001_encrypt_student_id_fields.php` confirms this was applied retroactively.

9. **Payment model has idempotency helpers.** `Payment::findByBmlReference()` static helper, `FINAL_STATUSES` constant, `isConfirmed()` method, and the `booted()` hook auto-syncing `local_id` ↔ `merchant_reference`.

10. **Role/permission system is in place** (Spatie) with a clear role hierarchy: `super_admin → admin → headmaster → supervisor → teacher → student → parent`.

11. **Security headers middleware** is applied globally.

12. **`RegistrationFlow` model** exists as a DB-backed session resume mechanism — good intent, though the writer side is incomplete.

---

## C. What Is Weak / Risky

### Architecture

- **No domain boundaries.** All 50+ controllers, 15+ services, and 66 models are in flat namespaces. Changes in one area can unintentionally affect another.
- **No Action classes.** Use-case logic is split between controllers and services. `CourseRegistrationController` does what could be 8–10 separate actions.
- **`CourseRegistrationController` is 1200+ lines** with 17 methods covering an 8-step wizard. It is the highest-risk file in the codebase.
- **Circular DI between `EnrollmentService` and `PaymentService`** — works currently via lazy `app()` resolution but is fragile.
- **`composer.json` autoload** only maps `App\` → `app/`. Adding `app/Domains/` requires no change (it falls under `App\`), but the intent is not explicit.

### Payment / Webhook

- **Duplicate enrollment-finalization code paths.** There are three places that can activate an enrollment after BML confirms: (1) `BmlWebhookController` via `PaymentService::handleCallback()`, (2) `PaymentController::return` via `PaymentService::finalizeByReference()`, and (3) `PaymentController::applyBmlTransactionStatus()` which duplicates enrollment-creation logic from `PaymentService` directly. While DB `lockForUpdate` provides idempotency, three paths are two too many.
- **Two active BML HTTP clients.** `BmlPaymentProvider` (canonical, via interface) and `BmlConnectService` (legacy, used directly in `PaymentController`) have divergent status-mapping constants and auth logic. Drift between them is a latent bug risk.
- **Two active webhook endpoints** — `/payments/bml/callback` and `/webhooks/bml`. Both call `PaymentService::handleCallback()`. Only `BmlWebhookController` performs IP allowlist checking; `PaymentController::callback()` skips it. If BML posts to both, each payment is processed twice (idempotent but wasteful and confusing).
- **`EnrollmentConfirmedMail` is sent synchronously** (not queued) inside the webhook handler. If the mail server is slow, BML's webhook call may time out.
- **`PaymentController` bypasses the `PaymentProviderInterface` abstraction** by injecting `BmlConnectService` directly alongside `PaymentService`.

### Controllers / Routes

- **Two closure routes** for `events` and `events/{event}` in `web_public.php` (query models inline). `PublicSite\EventController` already has `index()` and `show()` but is not wired.
- **`robots.txt` served by a route closure** — fine but should be a controller.
- **~10 controllers exist but are not wired to any route** (legacy scaffolding left in place).
- **15 legacy auth controllers** (`LoginController`, `RegisterController`, etc.) stub classes with no logic — misleading to future developers.

### N+1 Query Issues

- **`DashboardController` parent dashboard:** loops over `$children` and calls `->count()`, `->quranProgress()`, `->grades()` per child without eager loading. O(n) queries.
- **`Admin\EnrollmentController::export`:** `contacts()` relationship is not eager-loaded; queries `contacts()->where(...)` twice per row inside a loop.
- **`DashboardController::getStudentGrowthMetrics()`:** `Student::whereMonth(...)->count()` is called twice each for current and last month (4 queries where 2 would suffice).

### Frontend / CSS

- **Inline `style=` attributes dominate `home.blade.php`** with hardcoded hex values, contradicting the Tailwind token system.
- **`.btn-primary` and `.btn-secondary` component classes** are defined in `app.css` but not used on the homepage — duplication of the same visual styles via inline styles.
- **`app.css` has duplicate `@layer components` blocks** — the component classes are defined twice.
- **`resources/views/components/public/nav.blade.php` is 451 lines** — should be split into sub-components.
- **No consistent design system** across public views. Each page is styled independently with minor variations.
- **No Blade components for** course cards, CTA strips, badges, testimonial cards, stat blocks, empty states, breadcrumbs, or section shells.
- **`@tailwindcss/typography` is installed but not active** in the `plugins` array in `tailwind.config.js`.

### SEO / Content

- **Homepage meta description is static** in the layout template — not page-specific.
- **No schema.org markup** for Organization, Course, Article, Event, or BreadcrumbList.
- **No canonical URL tag** on most pages.
- **Policy pages** (terms/privacy/refunds) are rendered without branded layout — plain unstyled HTML.
- **`README.md` is the default Laravel README** — contains no project-specific documentation.

### Localization

- **Google Translate cookie-based language switching** rather than native Laravel locale routes — works but is not SEO-friendly (translated content is not served at locale-specific URLs for crawlers).
- **Some public views hardcode text** in English rather than using `__('public.key')`.
- **RTL layout** relies on CSS `[dir="rtl"]` overrides in `app.css` — functional but not comprehensively tested.

### Authorization

- **`ProfileUpdateRequest` has no `authorize()` method** — relies on route-level `auth` middleware only.
- **Admissions and contact form submission** have no explicit rate limiting or honeypot protection beyond what Breeze provides.
- **Admin routes** are protected by `role:admin|super_admin` middleware — correct, but no Gate/Policy classes exist for model-level authorization.

---

## D. Production-Readiness Risks

### Critical

| Risk | File(s) | Impact |
|------|---------|--------|
| Three enrollment-finalization code paths | `PaymentController`, `BmlWebhookController`, `PaymentService` | Confusing; one path skips IP check |
| Dual BML HTTP clients with divergent logic | `BmlConnectService`, `BmlPaymentProvider` | Latent bug if configs drift |
| Synchronous mail in webhook handler | `PaymentService::sendConfirmationEmail()` | BML webhook timeout risk |
| Two active webhook endpoints | `routes/web.php`, `web_public.php` | Double-processing; inconsistent IP guard |

### High

| Risk | File(s) | Impact |
|------|---------|--------|
| Encrypted field O(n) scan for duplicate detection | `EnrollmentService`, `CourseRegistrationController` | Degrades with scale |
| `RegistrationFlow` writer missing | `CourseRegistrationController` | `resume()` / `retryPayment()` always returns null |
| N+1 in parent dashboard | `DashboardController` | Performance at scale |
| N+1 in enrollment CSV export | `Admin\EnrollmentController::export` | Performance at scale |
| Fat `CourseRegistrationController` | `CourseRegistrationController` | High-risk to modify; hard to test |
| No exception handling in `bootstrap/app.php` | `bootstrap/app.php` | Raw exceptions may leak to users |
| Inline styles bypass token system | `home.blade.php` + multiple views | Hard to maintain brand consistency |

### Medium

| Risk | File(s) | Impact |
|------|---------|--------|
| Circular DI (EnrollmentService ↔ PaymentService) | Both service classes | Fragile; refactor risk |
| `PaymentController` bypasses interface | `PaymentController` | Mixes abstracted + concrete paths |
| `checkout_flow` session defaults silently | `CourseRegistrationController::setPassword()` | Silent `adult` fallback if session empty |
| No Form Requests for admissions/contact | `AdmissionController`, `ContactController` | Validation inline in controllers |
| No model-level Policies/Gates | Throughout | Authorization logic is only at route level |
| `@tailwindcss/typography` unused | `tailwind.config.js` | Policy/legal pages lack readable typography |
| Unused scaffolded controllers | ~10 controllers | Confusing codebase surface area |
| Legacy auth controller stubs | ~15 controllers | Dead code, misleading to developers |

### Low

| Risk | File(s) | Impact |
|------|---------|--------|
| No canonical URL on most pages | `public.blade.php` layout | Weak SEO |
| No schema.org markup | All public pages | Weak SEO |
| Static meta description in layout | `public.blade.php` | Not page-specific |
| Google Translate approach | `public.blade.php` | Translated pages not indexable per locale |
| No unit tests for domain services | `tests/` | Business logic coverage gap |
| `README.md` is default Laravel template | `README.md` | No developer onboarding |
| `robots.txt` closure route | `routes/web_public.php` | Minor — should be a controller |
| `lang-test.blade.php` + `test.blade.php` left in views | `resources/views/public/` | Dev artifacts in production views |

---

## E. Route Catalog

### `routes/web.php` — Bare (non-localized) routes

| Method | Path | Name | Middleware | Controller@Method |
|--------|------|------|-----------|-------------------|
| POST | `/payments/bml/callback` | `payments.bml.callback` | `web` (no CSRF) | `PaymentController@callback` |
| POST | `/webhooks/bml` | `webhooks.bml` | `web`, `throttle:120,1` (no CSRF) | `BmlWebhookController@__invoke` |
| GET | `/payments/bml/return` | `payments.bml.return` | `web` | `PaymentController@return` |
| GET | `/payments/status/{payment}` | `payments.status.by_id` | `web` | `PaymentController@statusByPayment` |

All other routes are wrapped in: `localeSessionRedirect`, `localizationRedirect`, `localeViewPath`

### `routes/web_public.php` — Public site (inside locale group)

| Method | Path | Name | Middleware | Controller@Method |
|--------|------|------|-----------|-------------------|
| GET | `/` | `public.home` | — | `PublicSite\HomeController@index` |
| GET | `/en`, `/ar`, `/dv` | — | — | closure (sets locale) |
| GET | `about` | `public.about` | — | `PublicSite\AboutController@index` |
| GET | `courses` | `public.courses.index` | — | `PublicSite\CourseController@index` |
| GET | `courses/{course}` | `public.courses.show` | — | `PublicSite\CourseController@show` |
| GET | `search` | `public.search` | — | `PublicSite\SearchController@index` |
| GET | `articles` | `public.articles.index` | — | `PublicSite\PostController@articlesIndex` |
| GET | `articles/{post:slug}` | `public.articles.show` | — | `PublicSite\PostController@show` |
| GET | `news` | `public.news.index` | — | `PublicSite\PostController@newsIndex` |
| GET | `news/{post:slug}` | `public.news.show` | — | `PublicSite\PostController@show` |
| GET | `events` | `public.events.index` | — | **closure** (queries `Event` model) |
| GET | `events/{event}` | `public.events.show` | — | **closure** (queries `Event` model) |
| GET | `events/{event}/calendar.ics` | `public.events.calendar` | — | `PublicSite\EventController@downloadCalendar` |
| GET | `gallery` | `public.gallery.index` | — | `PublicSite\GalleryController@index` |
| GET | `gallery/{gallery}` | `public.gallery.show` | — | `PublicSite\GalleryController@show` |
| GET | `admissions` | `public.admissions.create` | — | `PublicSite\AdmissionController@create` |
| POST | `admissions` | `public.admissions.store` | — | `PublicSite\AdmissionController@store` |
| GET | `admissions/thanks` | `public.admissions.thanks` | — | `PublicSite\AdmissionController@thanks` |
| GET | `apply` | `public.apply` | — | `PublicSite\AdmissionController@applyPage` |
| POST | `apply` | `public.apply.store` | — | `PublicSite\AdmissionController@store` |
| GET | `contact` | `public.contact.create` | — | `PublicSite\ContactController@create` |
| POST | `contact` | `public.contact.store` | — | `PublicSite\ContactController@store` |
| GET | `terms` | `public.terms` | — | `PolicyViewController@terms` |
| GET | `privacy` | `public.privacy` | — | `PolicyViewController@privacy` |
| GET | `refunds` | `public.refunds` | — | `PolicyViewController@refunds` |
| GET | `services` | `public.services` | — | `PolicyViewController@services` |
| GET | `page/{slug}` | `public.page.show` | — | `PublicSite\PageController@show` |
| GET | `sitemap.xml` | `public.sitemap` | — | `PublicSite\SitemapController@index` |
| GET | `robots.txt` | `public.robots` | — | **closure** |
| GET | `courses/{course}/checkout` | `courses.checkout.show` | — | `CourseRegistrationController@checkout` |
| POST | `courses/{course}/checkout/login` | `courses.checkout.login` | `throttle:10,1` | `CourseRegistrationController@checkoutLogin` |
| POST | `courses/register/start` | `courses.register.start` | `throttle:10,1` | `CourseRegistrationController@start` |
| GET | `courses/register/otp` | `courses.register.otp` | — | `CourseRegistrationController@otpForm` |
| POST | `courses/register/verify` | `courses.register.verify` | `throttle:10,1` | `CourseRegistrationController@verify` |
| POST | `courses/register/otp/resend-new` | `courses.register.otp.resend-new` | `throttle:5,1` | `CourseRegistrationController@resendNewRegistrationOtp` |
| GET | `courses/register/set-password` | `courses.register.set-password` | — | `CourseRegistrationController@passwordForm` |
| POST | `courses/register/set-password` | `courses.register.set-password.store` | — | `CourseRegistrationController@setPassword` |
| GET | `courses/register/continue` | `courses.register.continue` | — | `CourseRegistrationController@continueForm` |
| POST | `courses/register/enroll` | `courses.register.enroll` | `throttle:10,1` | `CourseRegistrationController@enroll` |
| GET | `courses/register/enroll/confirm` | `courses.register.enroll.otp` | — | `CourseRegistrationController@enrollOtpForm` |
| POST | `courses/register/enroll/confirm` | `courses.register.enroll.confirm` | `throttle:10,1` | `CourseRegistrationController@enrollConfirm` |
| POST | `courses/register/enroll/resend` | `courses.register.enroll.resend` | `throttle:5,1` | `CourseRegistrationController@enrollResendOtp` |
| GET | `courses/register/complete` | `courses.register.complete` | — | `CourseRegistrationController@complete` |
| GET | `courses/register/resume` | `courses.register.resume` | — | `CourseRegistrationController@resume` |
| GET | `courses/register/payment/retry` | `courses.register.payment.retry` | — | `CourseRegistrationController@retryPayment` |
| GET | `checkout/course/{course}` | `checkout.course.show` | `auth` | `CheckoutController@show` |
| POST | `payments/course/{course}/start` | `payments.course.start` | `auth` | `CheckoutController@start` |
| GET | `payments/bml/initiate` | `payments.bml.initiate` | — | `PaymentController@initiate` |
| GET | `payments/return/{payment}` | `payments.return` | — | `PaymentController@returnByPayment` |
| GET | `payments/ref/{merchant_reference}/status` | `payments.status` | — | `PaymentController@status` |
| GET | `portal/dashboard` | `portal.dashboard` | `auth` | `Portal\PortalController@dashboard` |
| GET | `portal/enrollments` | `portal.enrollments` | `auth` | `Portal\PortalController@enrollments` |
| GET | `portal/payments` | `portal.payments` | `auth` | `Portal\PortalController@payments` |
| GET | `portal/certificates` | `portal.certificates` | `auth` | `Portal\PortalController@certificates` |
| GET | `portal/profile` | `portal.profile` | `auth` | `Portal\PortalController@profile` |
| PUT | `portal/profile` | `portal.profile.update` | `auth` | `Portal\PortalController@updateProfile` |
| GET | `account/set-password` | `account.set-password` | `auth` | `AccountController@setPasswordForm` |
| POST | `account/set-password` | `account.set-password.store` | `auth` | `AccountController@setPassword` |
| GET | `my-enrollments` | `my.enrollments` | `auth` | `MyEnrollmentsController@index` |
| GET | `payments/{payment}/receipt` | `payment.receipt` | `auth` | `PaymentReceiptController@show` |

### `routes/web_localized.php` — Dashboard/Admin (inside locale group, requires `auth` + `trackActivity`)

| Method | Path | Name | Role Guard | Controller@Method |
|--------|------|------|-----------|-------------------|
| GET | `/dashboard` | `dashboard` | auth | `DashboardController@index` |
| GET | `/enhanced-dashboard` | `enhanced.dashboard` | auth | `EnhancedDashboardController@index` |
| Resource | `students` | `students.*` | auth | `StudentController` |
| GET | `students/{student}/quran-progress` | `students.quran-progress` | auth | `StudentController@quranProgress` |
| Resource | `teachers` | `teachers.*` | auth | `TeacherController` |
| Resource | `quran-progress` | `quran-progress.*` | auth | `QuranProgressController` |
| POST | `quran-progress/{progress}/update-progress` | `quran-progress.update-progress` | auth | `QuranProgressController@updateProgress` |
| Resource | `announcements` | `announcements.*` | auth | `AnnouncementController` |
| GET | `e-learning` | `e-learning.index` | auth | `ELearningController@index` |
| GET | `e-learning/quran` | `e-learning.quran` | auth | `ELearningController@quranLessons` |
| GET | `e-learning/arabic` | `e-learning.arabic` | auth | `ELearningController@arabicLessons` |
| GET | `e-learning/islamic-studies` | `e-learning.islamic-studies` | auth | `ELearningController@islamicStudies` |
| GET | `e-learning/{subject}` | `e-learning.show` | auth | `ELearningController@show` |
| GET | `/profile` | `profile.edit` | auth | `ProfileController@edit` |
| PATCH | `/profile` | `profile.update` | auth | `ProfileController@update` |
| DELETE | `/profile` | `profile.destroy` | auth | `ProfileController@destroy` |
| GET | `notifications` | `notifications.index` | auth | `NotificationController@index` |
| GET | `locale/{locale}` | `locale` | — | `LocaleController@setLocale` |
| GET | `analytics` | `analytics.index` | admin\|super_admin | `AnalyticsController@index` |
| GET | `analytics/reports` | `analytics.reports` | admin\|super_admin | `AnalyticsController@reports` |
| POST | `analytics/reports` | `analytics.reports.generate` | admin\|super_admin | `AnalyticsController@generateReport` |
| GET | `analytics/reports/{report}/download` | `analytics.reports.download` | admin\|super_admin | `AnalyticsController@downloadReport` |
| DELETE | `analytics/reports/{report}` | `analytics.reports.delete` | admin\|super_admin | `AnalyticsController@deleteReport` |
| Resource | `substitutions/absences` | `substitutions.absences.*` | multi-role | `Substitutions\TeacherAbsenceController` |
| Resource | `substitutions/requests` | `substitutions.requests.*` | multi-role | `Substitutions\SubstitutionRequestController` |
| POST | `substitutions/requests/{request}/take` | `substitutions.requests.take` | multi-role | `SubstitutionRequestController@take` |
| POST | `substitutions/requests/{request}/assign` | `substitutions.requests.assign` | multi-role | `SubstitutionRequestController@assign` |
| GET | `admin/users` | `admin.users.index` | super_admin | `Admin\AdminUserController@index` |
| DELETE | `admin/users/{user}` | `admin.users.destroy` | super_admin | `Admin\AdminUserController@destroy` |
| GET | `admin/enrollments` | `admin.enrollments.index` | admin+ | `Admin\EnrollmentController@index` |
| GET | `admin/enrollments/export` | `admin.enrollments.export` | admin+ | `Admin\EnrollmentController@export` |
| GET | `admin/enrollments/payments` | `admin.enrollments.payments` | admin+ | `Admin\EnrollmentController@payments` |
| GET | `admin/enrollments/{enrollment}` | `admin.enrollments.show` | admin+ | `Admin\EnrollmentController@show` |
| POST | `admin/enrollments/{enrollment}/activate` | `admin.enrollments.activate` | admin+ | `Admin\EnrollmentController@activate` |
| POST | `admin/enrollments/{enrollment}/reject` | `admin.enrollments.reject` | admin+ | `Admin\EnrollmentController@reject` |
| Resource | `admin/instructors` | `admin.instructors.*` | admin+ | `Admin\InstructorController` |
| GET | `admin/settings` | `admin.settings.index` | super_admin | `Admin\SettingsController@index` |
| POST | `admin/settings/clear-cache` | `admin.settings.clear-cache` | super_admin | `Admin\SettingsController@clearCache` |
| Resource | `admin/public-site/pages` | `admin.pages.*` | admin+ | `Admin\PublicSite\PageController` |
| Resource | `admin/public-site/courses` | `admin.courses.*` | admin+ | `Admin\PublicSite\CourseController` |

### `routes/api.php`

| Method | Path | Name | Middleware | Controller@Method |
|--------|------|------|-----------|-------------------|
| GET | `/api/user` | — | `auth:sanctum` | closure |
| GET | `/api/v2/health` | `api.v2.health` | — | `Api\SmsApiController@health` |
| POST | `/api/v2/sms/send` | `api.v2.sms.send` | — | `Api\SmsApiController@send` |
| GET | `/api/notifications` | `api.notifications.index` | `auth:sanctum` | `NotificationController@index` |
| GET | `/api/notifications/recent` | `api.notifications.recent` | `auth:sanctum` | `NotificationController@recent` |
| GET | `/api/notifications/unread-count` | `api.notifications.unread-count` | `auth:sanctum` | `NotificationController@unreadCount` |
| GET | `/api/notifications/stats` | `api.notifications.stats` | `auth:sanctum` | `NotificationController@stats` |
| POST | `/api/notifications/{id}/read` | `api.notifications.mark-read` | `auth:sanctum` | `NotificationController@markAsRead` |
| POST | `/api/notifications/read-all` | `api.notifications.read-all` | `auth:sanctum` | `NotificationController@markAllAsRead` |
| POST | `/api/notifications/test` | `api.notifications.test` | `auth:sanctum` | `NotificationController@sendTest` |

---

## F. Critical Flows

### A — OTP-First Auth / Account Resolver

```
User enters phone or email
  → ContactNormalizer normalizes the value
  → AccountResolverService::resolveOrCreateByContact()
      → Find UserContact where value = normalized
      → If found: return existing User + UserContact
      → If not found: create stub User + UserContact (is_verified = false)
  → OtpService::send(UserContact, 'login' | 'verify_contact')
      → SMS via SmsGatewayService (mobile) or Email via OtpEmailNotification
      → Store Otp record (hash, expiry, purpose, channel)
  → User enters OTP code
  → OtpService::verify(UserContact, purpose, code)
      → Hash::check(code, otp->code_hash)
      → Mark otp->used_at
      → Mark UserContact->is_verified = true
```

For **new registrations** (no DB user yet), the cache-backed path is used instead:
```
OtpService::sendForNewRegistration(type, value) → stores in cache
OtpService::verifyForNewRegistration(type, value, code) → checks cache
→ On success: create User + UserContact in DB
```

### B — Course Registration (Parent vs Adult 18+ Rule)

```
GET /courses/{course}/checkout
  → If logged in + verified + no force_password_change → /courses/register/continue
  → If not logged in → checkout form (new account or returning user)

POST /courses/register/start
  → StartRegistrationRequest validates name, contact, dob, gender
  → Under 18? → require guardian type + guardian info
  → AccountResolverService resolves contact
  → Send OTP (new: cache; existing: DB)
  → Redirect to /courses/register/otp

POST /courses/register/verify
  → Verify OTP
  → New user: create User + UserContact
  → force_password_change? → /courses/register/set-password
  → Else → /courses/register/continue

GET /courses/register/continue
  → Show enrollment form (courses, students, guardian info if parent)

POST /courses/register/enroll
  → Validate: courses selected, no duplicate enrollment, seat availability
  → Store enrollment data in session
  → Send enrollment consent OTP
  → Redirect to /courses/register/enroll/confirm

POST /courses/register/enroll/confirm
  → Verify consent OTP
  → processEnrollmentFromSession()
      → Free course: EnrollmentService::enrollAdultSelf() or enrollByParent()
      → Paid course: PaymentService::createPaymentForPendingEnrollment()
                     → PaymentService::initiatePayment() → BML redirect URL
```

### C — Payment Flow (Checkout → BML → Webhook Confirmation)

```
POST /courses/register/enroll/confirm (OTP verified)
  → PaymentService::createPaymentForPendingEnrollment()
      → Create Payment record (status: initiated)
      → Store enrollment form data in enrollment_pending_payload JSON
      → No RegistrationStudent or CourseEnrollment created yet
  → PaymentService::initiatePayment()
      → BmlPaymentProvider::initiate()
          → POST /v2/transactions to BML API
          → Returns redirect URL
      → Update Payment: payment_url, status=pending
  → Redirect user to BML payment portal

BML payment portal (external)
  → User completes payment
  → BML redirects to GET /payments/bml/return?ref=...

GET /payments/bml/return
  → PaymentController@return
  → Reads ?ref= from query string (no session dependency)
  → PaymentService::finalizeByReference(merchant_reference)
      → Queries BML for transaction status
      → If confirmed: applyVerifiedResult() → creates enrollment, sends notifications
      → Shows processing/success page to user

POST /webhooks/bml (BML server-side webhook)
  → BmlWebhookController@__invoke
  → IP allowlist check
  → HMAC signature verification
  → PaymentService::handleCallback(payload)
      → applyVerifiedResult() → idempotent (lockForUpdate check)
      → Creates enrollment if enrollment_pending_payload exists
  → Returns HTTP 200 immediately
```

### D — Enrollment Activation After Payment

```
PaymentService::applyVerifiedResult(Payment, VerifiedCallbackResult)
  → DB lockForUpdate on Payment
  → If already confirmed: return (idempotent)
  → Update Payment: status=confirmed, confirmed_at, webhook_payload
  → If enrollment_pending_payload exists:
      → EnrollmentService::createEnrollmentForConfirmedPayment(Payment)
          → Create RegistrationStudent (if not existing)
          → Create CourseEnrollment (status=active, payment_status=confirmed)
          → Create PaymentItem (links Payment → CourseEnrollment → Course)
          → Clear enrollment_pending_payload
  → If PaymentItems already exist (legacy flow):
      → Update CourseEnrollment: status=active, payment_status=confirmed
  → Send confirmation notifications (email + SMS, admin email + SMS)
```

### E — Portal (Student Dashboard)

```
GET /portal/dashboard → PortalController@dashboard
  → Loads: user, enrollments with course+payment, certificates count
  → Renders portal/dashboard.blade.php

GET /portal/enrollments → PortalController@enrollments
  → Loads: all enrollments ordered by enrolled_at
  → Renders portal/enrollments.blade.php

GET /portal/payments → PortalController@payments
  → Loads: all payments with items+courses
  → Renders portal/payments.blade.php

GET /payments/{payment}/receipt → PaymentReceiptController@show
  → Verifies payment belongs to auth user
  → Renders payments/receipt.blade.php
```

### F — Admin Dashboard Modules

| Module | Route Prefix | Role Guard |
|--------|-------------|-----------|
| Student management | `students` | auth |
| Teacher management | `teachers` | auth |
| Quran progress | `quran-progress` | auth |
| Announcements | `announcements` | auth |
| E-Learning | `e-learning` | auth |
| Analytics & Reports | `analytics` | admin\|super_admin |
| Substitutions | `substitutions/absences`, `substitutions/requests` | multi-role |
| User management | `admin/users` | super_admin |
| Enrollment management | `admin/enrollments` | admin+ |
| Instructor management | `admin/instructors` | admin+ |
| Settings | `admin/settings` | super_admin |
| CMS Pages | `admin/public-site/pages` | admin+ |
| CMS Courses | `admin/public-site/courses` | admin+ |

### G — Notifications APIs

```
GET /api/notifications               → paginated list (auth:sanctum)
GET /api/notifications/recent        → last 10 (auth:sanctum)
GET /api/notifications/unread-count  → {count: n} (auth:sanctum)
GET /api/notifications/stats         → {total, unread, read, by_type} (auth:sanctum)
POST /api/notifications/{id}/read    → mark one as read (auth:sanctum)
POST /api/notifications/read-all     → mark all as read (auth:sanctum)
POST /api/notifications/test         → send test notification (auth:sanctum)
```

### H — SMS API v2

```
GET  /api/v2/health        → SmsApiController@health (no auth — health check only)
POST /api/v2/sms/send      → SmsApiController@send
    → API key auth handled inside controller (not middleware)
    → Validates: to (phone), message (max 1600 chars)
    → Calls SmsGatewayService::sendSms()
    → Returns {success, message_id, status}
```

---

## G. Coupling Hotspots

| Hotspot | Risk | Reason |
|---------|------|--------|
| `CourseRegistrationController` | Very High | 1200+ lines, 17 methods, direct service calls, session management, form logic all in one class |
| `PaymentController` | High | Injects both abstract `PaymentService` and concrete `BmlConnectService`; 3 distinct enrollment-finalization paths |
| `DashboardController` | Medium-High | 500+ lines; N+1 loops; role-switched dispatch logic |
| `EnrollmentService` ↔ `PaymentService` | Medium | Circular DI via lazy `app()` resolution |
| `BmlConnectService` ↔ `BmlPaymentProvider` | Medium | Duplicate BML HTTP logic; divergent status constants |
| `home.blade.php` | Low-Medium | All public homepage layout is inline styles — no reusable components |
| `public/nav.blade.php` | Low-Medium | 451 lines, single component |

---

## H. Recommended Refactor Order

Based on risk, coupling, and dependency graph:

1. **Fix critical payment path first** (consolidate 3 enrollment-finalization paths → 1; unify BML clients; queue the confirmation email)
2. **Move event closure routes to EventController** (lowest risk change, immediate cleanup)
3. **Scaffold `app/Domains/` structure + providers** (no behavior changes)
4. **Extract Payments domain** (highest risk, highest reward — PaymentGatewayInterface, PaymentStateMachine, WebhookIdempotency)
5. **Extract Enrollment activation via event** (decouple from payment code paths)
6. **Split `CourseRegistrationController` into Actions** (most complex, do last among backend work)
7. **Build public design system** (Blade components, Tailwind cleanup, tokens)
8. **Redesign public pages** (homepage, courses, admissions, contact, about, content pages)
9. **SEO / accessibility / performance pass**
10. **Documentation + full test suite update**

---

## I. Deep Audit — Security

> Added: March 2026 deep-dive audit

### CSRF Exemptions (`bootstrap/app.php:15-18`)

Two POST endpoints are CSRF-exempt: `payments/bml/callback` and `webhooks/bml`. Both are webhook endpoints that **must** be CSRF-exempt. BML webhook signature validation is present in `BmlWebhookController`.

### SMS API v2 Authentication (`app/Http/Controllers/Api/SmsApiController.php:103-118`)

- **Good:** Uses `hash_equals()` for constant-time API key comparison.
- **Good:** Supports `X-API-Key` header and `Authorization: Bearer` token.
- **Issue:** SMS API endpoints (`routes/api.php:24-26`) have **no middleware authentication** — relies solely on the controller's `validateApiKey()` method.
- **Issue:** No rate limiting configured on SMS API routes. A compromised or guessed API key enables unlimited SMS sends.

### Rate Limiting

| Scope | Limit | Location |
|-------|-------|----------|
| OTP sends | 5 per 60 min per contact | `OtpService.php:15-16` |
| OTP verify attempts | 10 per 15 min per contact | `OtpService.php:18-19` |
| OTP resend cooldown | 30 seconds | `OtpService.php:17` |
| Login brute-force | 10 per 15 min | `CourseRegistrationController:71-76` |

### Webhook Security (`app/Http/Controllers/BmlWebhookController.php`)

- **Good:** IP allowlist check (line 28) and HMAC signature verification.
- **Concern:** Returns HTTP 200 even on application errors (lines 45-47) — BML won't know about failures and cannot retry.

### Session Stores Sensitive Data

| Data | Location | Risk |
|------|----------|------|
| Password hash | `CourseRegistrationController:191` | Session hijack exposes hash |
| Child password | `CourseRegistrationController:722-724, 888, 950` | Parent enrollment flow |
| National ID, passport, DOB | Session `reg_pending_data` | PII exposure on session leak |

Session is encrypted by default (`SESSION_ENCRYPT`), but storing passwords and PII in session remains a defense-in-depth concern.

### National ID & Passport Encryption (`app/Models/RegistrationStudent.php:29-30`)

- **Good:** `national_id` and `passport` use Laravel's `encrypted` cast.
- **Missing:** No key rotation mechanism visible.

---

## J. Deep Audit — Database

### Email Nullable + Unique Constraint

`users` table: `email` is `unique()` but `nullable` (via `2026_02_16_000010_make_users_email_nullable_for_mobile_auth.php`). Multiple NULL values technically violate uniqueness semantics (MySQL allows this; PostgreSQL does not). Users registering via mobile-only OTP cannot have email validation.

**Fix:** Remove unique constraint and add a partial unique index, or enforce email at the application level.

### Raw MySQL-Specific SQL (`2026_02_16_000007_create_course_enrollments_table.php:25-26`)

Generated column with `IFNULL()` and `STORED` keyword is MySQL-specific. Will fail on SQLite (used in tests) and PostgreSQL.

**Fix:** Move `term_key` computation to application level or use database abstraction.

### Missing Soft Deletes

No soft delete migrations exist across the codebase. Hard deletes on `users`, `payments`, and `course_enrollments` destroy audit trail data.

### Dangerous Cascade Deletes

`payments.user_id → users (onDelete: cascade)` — deleting a user cascades to **all their payment records**. This is dangerous for financial data that may need to be retained.

### Missing Indexes

| Table | Column(s) | Used By | Impact |
|-------|-----------|---------|--------|
| `user_contacts` | `value` | Contact lookups (login, OTP) | Full table scan |
| `user_contact_otps` | `(user_contact_id, purpose)` | `OtpService::verify()` | Slow OTP lookups |
| `course_enrollments` | `(student_id, course_id, status)` | Status queries, duplicate checks | Full scan on enrollment checks |
| `payments` | `(user_id, status)` | User payment history | Full scan for portal pages |

### JSON Column Usage

`Payment` model stores `callback_payload`, `webhook_payload`, `bml_status_raw`, and `enrollment_pending_payload` as JSON. Appropriate for varying BML response structures, but `enrollment_pending_payload` contains sensitive registration data — ensure it is cleared after enrollment creation (it is, in `EnrollmentService`).

---

## K. Deep Audit — Queue & Jobs

### No Async Job Dispatch Found

All work is performed synchronously in the request lifecycle.

### Synchronous Mail

`PaymentService::sendConfirmationEmail()` sends mail synchronously inside webhook/return handlers. A slow mail server blocks the response.

**Fix:** Dispatch as queued job: `Mail::to($user)->queue(new EnrollmentConfirmedMail(...))`.

### Synchronous SMS

`OtpService::dispatchCode()` sends SMS synchronously. The HTTP timeout is 30 seconds (`SmsGatewayService`). A slow SMS provider blocks the OTP request for up to 30 seconds.

**Fix:** Dispatch SMS sending to the queue with retry logic.

### Queue Backend

Database driver (`QUEUE_CONNECTION=database`). Database failure = queue failure. No external monitoring for failed jobs.

**Consider:** Redis for queue + cache backends when scaling beyond a single server.

### Scheduled Commands (`routes/console.php`)

| Command | Schedule | Purpose |
|---------|----------|---------|
| `payments:reconcile --older-than=5 --not-updated-in=2` | Every 10 min | Fallback for missed BML webhooks |
| `akuru:prune-expired` | Hourly | Remove expired OTPs and stale draft/pending enrollments |
| `akuru:scheduler-heartbeat` | Every minute | Cron health check |

No error logging visible in console routes.

---

## L. Deep Audit — Caching

### Current Cache Usage

- **Backend:** Database store (`CACHE_STORE=database`).
- **Used in:** `SmsGatewayService` (rate limits), `OtpService` (rate limiting via `RateLimiter`), `PublicSite\HomeController`, `SchedulerHealthCheckCommand`, `ServerStatusCommand`, `AppServiceProvider`.

### Missing Caching Opportunities

| Data | Access Pattern | Recommendation |
|------|---------------|----------------|
| Course listings | Read-heavy, rarely changes | Cache with tag invalidation on admin update |
| Course categories | Read-heavy, rarely changes | Cache indefinitely, invalidate on change |
| Settings table | Read on every request (if used) | Cache on boot, invalidate on admin save |
| Public page content | Read-heavy | Cache with 5-minute TTL |

---

## M. Deep Audit — Error Handling

### No Central Exception Handler

`bootstrap/app.php:38-40` has an empty exception handling section. No custom exception handlers, no custom error responses for API endpoints.

### Silent Error Swallowing

`BmlWebhookController` (lines 42-45) catches all exceptions from `handleCallback()`, logs them, but returns HTTP 200. BML receives success and will not retry.

**Fix:** Return HTTP 500 for server errors; only return 200 for idempotent-processing or validation errors.

### Try/Catch Coverage

Found in registration validation, `EnrollmentService`, `PaymentService`, and `OtpService`. OTP service cleans up on failure (good). Other services log but may not surface errors to the user.

---

## N. Deep Audit — Config & Environment

### Custom Config Files

| File | Purpose | Notes |
|------|---------|-------|
| `config/bml.php` | BML payment gateway | Auth modes: raw, bearer_jwt, bearer_basic, auto. Timeout: 30s. |
| `config/registration.php` | Registration defaults | Only `REGISTRATION_DEFAULT_COUNTRY_CODE` (960 for Maldives) |
| `config/laravellocalization.php` | i18n | 30+ languages configured |
| `config/permission.php` | Spatie RBAC | Multi-tenancy disabled |

### Environment Variable Confusion

- `SMS_USE_DHIRAAGU` vs `SMS_GATEWAY_ENABLED` — two flags for SMS behavior may conflict.
- `SMS_GATEWAY_API_KEY` is referenced in `SmsApiController` but not documented in `.env.example`.
- BML config spreads across many env vars: `APP_ID`, `API_KEY`, `MERCHANT_ID`, `WEBHOOK_SECRET`, etc.

---

## O. Deep Audit — Mail & Notifications

### Mail Classes (`app/Mail/`)

| Class | Trigger |
|-------|---------|
| `EnrollmentConfirmedMail` | Payment confirmed (paid courses) |
| `FreeEnrollmentConfirmedMail` | Free course enrollment |
| `EnrollmentStatusMail` | Admin activates/rejects enrollment |
| `AdminNewEnrollmentMail` | New enrollment notification to admin |
| `AdminFreeEnrollmentMail` | Free enrollment notification to admin |

### Notification Classes (`app/Notifications/`)

| Class | Channel |
|-------|---------|
| `NewAdmissionApplication` | Admin notification |
| `NewContactMessage` | Admin notification |
| `OtpEmailNotification` | Email OTP delivery |

**Inconsistency:** 5 mail classes vs 3 notification classes. No notification fallback across channels (SMS + Email).

---

## P. Deep Audit — Session Usage

### Scale of Session Usage

**66 `session()` calls** in `CourseRegistrationController` alone.

### Session Keys

| Key | Content | Lifecycle |
|-----|---------|-----------|
| `pending_selected_course_ids` | Array of course IDs | Multi-step enrollment |
| `pending_term_id` | Term selection | Multi-step enrollment |
| `checkout_flow` | `adult` / `parent` / `guardian` | Flow type |
| `reg_pending_data` | Full registration form (name, DOB, contact, password hash) | Until enrollment complete |
| `enroll_pending_*` | Enrollment workflow state | Until OTP confirmed |
| `enroll_otp_*` | OTP verification state | Until verified |

### Concerns

- Session payload could exceed typical limits for complex enrollments with many courses.
- Cleanup only occurs in `clearEnrollPendingSession()` (line 1040-1042) — registration session data may persist if the user abandons mid-flow.
- `RegistrationFlow` model was designed to replace session-based state, but its writer side is incomplete.

---

## Q. Deep Audit — File Upload & Storage

### Upload Handling

29 controllers handle file uploads: `TeacherController`, `StudentController`, `HeroBannerController`, `MediaItemController`, `GalleryController`, `PostController`, and others.

### Storage Configuration (`config/filesystems.php`)

| Disk | Path | Usage |
|------|------|-------|
| `local` | `storage/app/private` | Default |
| `public` | `storage/app/public` → `/storage` | Public assets |
| `s3` | Configured but not enabled | CDN-ready |

### Concerns

- No centralized file upload validation visible — each controller handles validation independently.
- `intervention/image` v3.11 used for image processing — verify max upload size limits are configured.
- Unclear which uploads go to which disk.

---

## R. Deep Audit — Dependencies

### Key Package Versions (`composer.json`)

| Package | Version | Notes |
|---------|---------|-------|
| `php` | ^8.2 | Modern, good |
| `laravel/framework` | ^12.0 | Latest major |
| `laravel/breeze` | ^2.3 | Auth scaffolding |
| `spatie/laravel-permission` | ^6.21 | RBAC |
| `laravel/socialite` | ^5.23 | OAuth (unused in routes?) |
| `intervention/image` | ^3.11 | Image processing |
| `laravel-notification-channels/fcm` | ^5.1 | Firebase Cloud Messaging |
| `guzzlehttp/guzzle` | ^7.10 | HTTP client |
| `mcamara/laravel-localization` | ^2.3 | i18n |
| `alkoumi/laravel-hijri-date` | ^1.0 | Islamic calendar |
| `phpunit/phpunit` | ^11.5.3 | Testing |
| `laravel/pint` | ^1.24 | Code linting |

No immediately outdated or risky dependencies. Run `composer outdated` regularly.

---

## S. Deep Audit — Hardcoded Values & Magic Numbers

| Value | Meaning | Location | Recommendation |
|-------|---------|----------|----------------|
| 5 | Max OTP sends per hour | `OtpService.php:15` | Config |
| 60 | OTP send window (minutes) | `OtpService.php:16` | Config |
| 30 | Resend cooldown (seconds) | `OtpService.php:17` | Config |
| 10 | Max verify attempts | `OtpService.php:18` | Config |
| 15 | Verify window (minutes) | `OtpService.php:19` | Config |
| 10 | Login attempts per 15 min | `CourseRegistrationController:72` | Config |
| 5 | OTP expiry (minutes, short) | `Otp.php:68` | Config |
| 15 | OTP expiry (minutes, long) | `Otp.php:69` | Config |
| 18 | Minimum age for adult enrollment | Multiple locations | Config (`registration.php`) |
| 30 | SMS HTTP timeout (seconds) | `SmsGatewayService` | Config (`services.php`) |

**Fix:** Extract all timing/limit constants to `config/registration.php` or a new `config/limits.php`.

---

## T. Refactoring Priority Matrix (Updated)

### Phase 1 — Critical (Security & Data Integrity)

1. Remove sensitive data (passwords, PII) from session storage — use encrypted temporary DB records or `RegistrationFlow` model
2. Add rate limiting middleware to SMS API endpoints
3. Improve webhook error handling — return 5xx on server errors
4. Add soft deletes to `users`, `payments`, `course_enrollments`, `registration_students`
5. Fix email nullable + unique constraint
6. Change `payments.user_id` cascade to `set null` (retain financial records)

### Phase 2 — High (Performance & Reliability)

1. Queue mail and SMS dispatch (implement `ShouldQueue` on mail classes)
2. Add missing database indexes (see section J)
3. Remove MySQL-specific raw SQL from migrations
4. Implement caching for course listings, categories, settings
5. Fix N+1 queries in dashboard and admin export

### Phase 3 — Medium (Architecture & Maintainability)

1. Consolidate 3 enrollment-finalization paths to 1
2. Unify BML HTTP clients (`BmlConnectService` → `BmlPaymentProvider`)
3. Extract magic numbers to config files
4. Complete `RegistrationFlow` model (replace session-based wizard state)
5. Add centralized exception handling in `bootstrap/app.php`
6. Implement centralized file upload validation

### Phase 4 — Low (Cleanup & Polish)

1. Remove unused/legacy controllers (~25 controllers)
2. Move event closure routes to `EventController`
3. Document env vars consistently in `.env.example`
4. Add unit tests for domain services
5. Update `README.md` with project-specific documentation
