# Status

## Phase 0 — Foundation (complete)

### 0.1 Install & tooling
- Inertia + React, Pest, Larastan, ADR template, CI (`pint` + `pest`)

### 0.2 Domain skeleton
- 20 domain providers + ADR-001 (single-institute, `school_id` retained)

### 0.3 Move map (namespace/route only)
1. **Website** — public CMS
2. **Settings** — `Setting`, admin settings
3. **Identity** — OTP, contacts, `Auth/*`, `User`
4. **People** — students, teachers, guardians
5. **Academics** — classes, timetable, attendance, substitutions
6. **Hifz** — frozen namespace move + `routes.php`
7. **Admissions** — enrollment, registration, applications
8. **Finance** — payments, BML
9. **Notifications** — SMS, in-app notifications
10. **Media** — WebP, gallery models
11. **Portal** — portal + dashboard controllers
12. **Academics/Legacy** — deprecated e-learning stubs
13. **Settings** — analytics (ADR-002)

Also: **Courses**, **HR**, **Support** (`LocaleController`, `IslamicCalendarService`).

### 0.4 Contracts
- `SmsSenderInterface`, `PushSenderInterface` (null stub)
- `ImageProcessorInterface`, `MediaStorageInterface`
- `PaymentProviderInterface` in Finance\Contracts
- `DocumentRendererInterface` stub in Support

### 0.5 Architecture tests
- `tests/Architecture/DomainBoundariesTest.php` (CI-blocking via `pest`)

### 0.6 Cleanups
- Root deployment/readme MDs → `docs/legacy/`
- Duplicate OTP migration: **deferred** (verify prod schema before squash)

### 0.7 Definition of done
- `app/Models` and `app/Services` empty; `app/Http/Controllers` = `Controller.php` only
- 64 Pest tests green (feature + architecture + route snapshots)
- Inertia smoke route `/inertia-test` retained for production build verification
- Production deploy: **pending operator**

## Next (Phase S1)

- Student unification and course engine per `docs/S1_SPEC.md`
