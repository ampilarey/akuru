# Phase 0 — Foundation Checklist

**Goal:** Restructure `ampilarey/akuru` into the modular monolith with zero user-visible behavior change. Everything here is mechanical. When Phase 0 is done, the site works exactly as before, but the codebase is ready for S1 and the L/W/engine tracks.

**Companion docs:** `ROADMAP.md` (architecture), `SPEC.md` (course engine), `S1_SPEC.md` (next phase).

**Rule for every step:** commit per step, deploy-safe at every commit, no logic changes "while you're there."

---

## 0.1 Install & tooling

| # | Task | Notes |
|---|---|---|
| 1 | `composer require inertiajs/inertia-laravel` + publish middleware (`HandleInertiaRequests`) | Blade and Inertia coexist; register middleware in `web` group |
| 2 | `npm i react react-dom @inertiajs/react @vitejs/plugin-react` | Add react plugin to `vite.config.js` alongside existing build; keep current Tailwind 3 setup |
| 3 | Create `resources/js/app.jsx` Inertia bootstrap + `resources/views/app.blade.php` Inertia root | Existing Blade layouts untouched |
| 4 | Smoke route: `/inertia-test` rendering one React page | Delete after verification |
| 5 | `composer require pestphp/pest pestphp/pest-plugin-laravel pestphp/pest-plugin-arch --dev` then `pest --init` | Keep PHPUnit tests running; Pest runs both |
| 6 | `composer require larastan/larastan --dev` (level 5 to start) | Optional but cheap now |
| 7 | Add `STATUS.md` and `docs/adr/` (template: context → decision → consequences) | §57 discipline starts now |
| 8 | CI pipeline: `pint --test` + `pest` + (optional) `phpstan` must pass | GitHub Actions |

## 0.2 Domain skeleton

Create folders + a `ServiceProvider` per domain (register routes, migrations path, bindings). Register all providers in `bootstrap/providers.php`.

```
app/Domains/{Identity, People, Academics, ExamsGrades, Hifz, Admissions,
            Finance, HR, Commerce, Library, Courses, Offerings, Progress,
            Pronunciation, Media, Notifications, Portal, Website, Settings}
app/Support/           (shared base classes, DTO base)
```

Each domain: `Actions/ Models/ Services/ DTOs/ Events/ Contracts/ Http/Controllers/ Http/Requests/ Policies/ Database/migrations/ Providers/ routes.php` — create subfolders only when first used; empty domains may hold just the provider.

ADR-001: record the domain map and the single-vs-multi-branch decision (recommendation: single-institute; keep `school_id` columns as future-safe reference, no tenancy logic).

## 0.3 Move map (namespace/route updates ONLY — no behavior change)

Move in this order (lowest-risk first), one commit each, full test run after each:

| # | Current code | Destination domain |
|---|---|---|
| 1 | `Models/{Page,Post,Banner,Faq,Testimonial,GalleryImage,ContactMessage,ContactInquiry,Event,EventRegistration}` + `Controllers/{Public*,ContactMessage*,...}` + `views/public` references | **Website** |
| 2 | `Models/Setting` + `Admin/Settings*` | **Settings** |
| 3 | `Services/{OtpService,ContactNormalizer,AccountResolverService}`, `Models/{UserContact,Otp}`, `Controllers/Auth/*` | **Identity** |
| 4 | `Models/{Student,RegistrationStudent,Teacher,ParentGuardian}` + guardians pivots + Student/Teacher controllers | **People** |
| 5 | `Models/{ClassRoom,Subject,Timetable,Period,AcademicYear,Attendance,Grade,TeacherAbsence,Substitution*,Announcement,AbsenceNote,CoursePlan,PlanTopic,LessonLog}` + Substitutions/Announcement controllers | **Academics** (stubs move too; they get built in S1–S3) |
| 6 | `Services/Hifz/*`, `Controllers/Hifz/*`, all Hifz + Quran source models (`Surah,QuranAyah,QuranWord,QuranMushaf,QuranPage,QuranWordPosition,RecitationPractice,QuranProgress`), `routes/hifz.php` | **Hifz** (frozen — namespace only) |
| 7 | `Models/AdmissionApplication` + admission/registration controllers + `Services/Enrollment/*` | **Admissions** |
| 8 | `Services/Payment/*`, `BmlConnectService`, `Models/{Invoice,FeeItem,InvoiceLine,Payment}` + payment/receipt controllers | **Finance** |
| 9 | `Services/{NotificationService,SmsGatewayService}` + FCM channel + notification models/controllers | **Notifications** |
| 10 | `Services/WebPImageService` + media/gallery upload logic | **Media** |
| 11 | `Controllers/Portal/*` + portal views wiring | **Portal** |
| 12 | `Controllers/{ELearningController, Quiz*, Assignment*}` + models | **Academics/Legacy/** subfolder (marked deprecated; replaced in Phase 1A/2) |
| 13 | `AnalyticsService` + analytics controllers | **Settings** or split later — record choice in ADR |

Route files: split `routes/web_localized.php` registrations into per-domain `routes.php` loaded by each provider; URLs MUST NOT change (assert in tests: route-name snapshot before/after).

## 0.4 Contracts in front of concretes

Create interface + container binding; change call-sites to the interface (mechanical):

| Contract | First implementation |
|---|---|
| `Notifications/Contracts/SmsSenderInterface` | existing `SmsGatewayService` |
| `Notifications/Contracts/PushSenderInterface` | existing FCM channel |
| `Media/Contracts/ImageProcessorInterface` | existing `WebPImageService` |
| `Media/Contracts/MediaStorageInterface` | Laravel storage wrapper (private + public disks) |
| `Finance/Contracts/PaymentProviderInterface` | already exists — move into Finance domain, keep pattern |
| `Support/Contracts/DocumentRendererInterface` | stub now (PDF receipts exist); S3/L-track implement |

## 0.5 Architecture tests (CI-blocking from day one)

`tests/Architecture/`:

1. Domains do not use other domains' `Models\*` (allow-list: own domain + `App\Support`).
2. Only `Contracts`, `DTOs`, `Events`, `Actions` of a domain may be referenced cross-domain.
3. No domain references `BmlConnectService`, `SmsGatewayService`, `WebPImageService` concretes (must use contracts) — except their home domain.
4. Controllers don't use `DB::` facade and have no `private function` business logic (heuristic: max method length) — enforce on NEW controllers only at first; legacy controllers get a baseline ignore-list that may only shrink.
5. `Hifz` domain referenced by no other domain except Portal contracts.
6. No code outside Commerce touches wallet/gift-card tables (activates in L4).

## 0.6 Cleanups (safe now)

- [ ] Squash/remove duplicate `2025_10_15*create_otps_table` migration (keep 2026 user_contacts-based OTP) — verify prod schema first.
- [ ] Move the ~10 root-level deployment/readme MD files to `docs/legacy/`.
- [ ] Commit `ROADMAP.md`, `SPEC.md`, library plan, `S1_SPEC.md` into `docs/`.
- [ ] Add `.env.example` entries for any binding configs introduced.

## 0.7 Definition of Done

- [ ] All routes respond identically (route-name snapshot test green; manual smoke of: home, courses, course detail, registration+BML sandbox, Hifz dashboards, portal, admin login).
- [ ] `app/Models` and `app/Services` are EMPTY (everything has a domain); `app/Http/Controllers` contains only thin shared infrastructure (if any).
- [ ] Pest green including arch tests; PHPUnit suite still green; CI enforces.
- [ ] One React page renders via Inertia in production build.
- [ ] STATUS.md updated; ADR-001 (domain map + branch decision) recorded.
- [ ] Production deploy completed with zero user reports.

**Explicitly out of scope for Phase 0:** any schema migration, any Hifz logic change, any UI change, the Student unification (that's S1).
