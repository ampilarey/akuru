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
            Pronunciation, Media, Notifications, Portal, Website, Settings, PrayerTimes}
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

Route files: split `routes/web_localized.php` registrations into per-domain `routes.php` loaded by each provider; URLs MUST NOT change (assert in tests: route-name snapshot before/after). **Status (2026-06): deferred to early S1 infrastructure slice** — route-name snapshot tests are in place; centralized route files remain.

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

- [x] ~~Squash/remove duplicate `2025_10_15*create_otps_table` migration~~ — **premise was wrong** (audit 2026-08-26). The two migrations are not duplicates: `2025_10_15_161251` creates `otps`; the similarly-named `2026_02_16_000002` creates `user_contact_otps`. Only the filenames matched. The legacy `otps` table was dead (`Models\Otp` reads `user_contact_otps`; the last reference was a stale truncate in `ClearNonAdminUsers`) and is now dropped forward by `2026_08_26_000001_drop_legacy_otps_table`. The 2025 migration file stays — deleting a migration that has already run desyncs the `migrations` table. Note left open: `2025_10_15_161402` also added `users.otp_enabled` / `two_factor_enabled` / `phone_verified_at`, which appear unused; not dropped in this pass.
- [x] Move the ~10 root-level deployment/readme MD files to `docs/legacy/`.
- [x] Commit `ROADMAP.md`, `SPEC.md`, library plan, `S1_SPEC.md` into `docs/`.
- [x] Add `.env.example` entries for any binding configs introduced.

## 0.7 Definition of Done

- [x] Route-name snapshot tests green (automated). [ ] Manual smoke (home, courses, registration+BML sandbox, Hifz, portal, admin) — **pending operator on staging**.
- [x] `app/Models` and `app/Services` **removed** (directories do not exist; code lives in `app/Domains/*`); `app/Http/Controllers` = base `Controller.php` plus `Api/TestDeployWebhookController` only. The webhook is **app infrastructure, not domain logic** (it fast-forwards the TEST checkout; host-guarded by `config/deploy.php`), so it stays out of the domains deliberately — filing it under a domain would misrepresent it, and creating an Ops domain for one controller would violate ROADMAP §7 "don't over-split domains early". Accepted exception, recorded in the audit 2026-08-26.
- [x] Pest green including arch tests on `main` CI (Pint + Pest block; PHPStan informational only).
- [x] Inertia smoke route `/inertia-test` exists for production build verification.
- [x] STATUS.md updated; ADR-001 recorded.
- [x] Per-domain `routes.php` split — **dropped, not deferred** (audit 2026-08-26). It was deferred "to early S1" and then skipped through S1–S5, 1A–1B, Phase 2 and the A-track without causing a problem. Central `routes/web_localized.php` + `routes/web_public.php` are the accepted end state; the route-name snapshot suite (`tests/Feature/Routes/`, 6 files) is the guard that matters. `app/Domains/Hifz/routes.php` exists as a one-off and is not a precedent. Revisit only if route volume becomes unmanageable.
- [ ] Staging/production deploy — **pending operator** (staging `test.akuru.edu.mv` first).

**Explicitly out of scope for Phase 0:** any schema migration, any Hifz logic change, any UI change, the Student unification (that's S1).
