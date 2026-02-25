# Modular Monolith — Implementation Guide

> **Execution directive:** Work through phases **in order**. The repository must remain runnable
> and all tests must pass after every phase commit. Do not skip ahead. Do not batch multiple
> phases into a single commit.

---

## What We're Building

Convert the current flat Laravel structure into a **Modular Monolith** under `app/Domains/`.
One app, one database — better internal boundaries so changes in Payments cannot accidentally
break Enrollment, and changes in OTP cannot break the public site.

---

## Non-Negotiables

1. **Preserve all existing route paths AND request/response payload shapes** unless a change is explicitly versioned.
2. **Routes only route. Controllers only thin-orchestrate.** All business logic lives in Domain Actions or Services.
3. **Webhook and return URLs must remain stable and must NOT be locale- or session-dependent.** The BML return URL never confirms a payment — it only shows a status page. Confirmation is the webhook's job only.
4. **Cross-domain calls only via Events/Listeners OR Contracts + DI.** No direct service-to-service coupling across domain boundaries.
5. **Refactor in safe commits.** The repo must stay runnable and tests must pass after each phase.
6. **No secrets committed.** `.env` is gitignored; only `.env.example` exists in the repo.
7. **Do NOT move Eloquent models out of `App\Models`** unless proven safe against stored morph strings in the database.
8. **Deferred enrollment:** `RegistrationStudent` + `CourseEnrollment` are created only after BML confirms payment via webhook.
9. **BML payment state transitions are idempotent.** Receiving the same webhook twice must not create duplicate enrollments or double-charge.

---

## Target Domain Structure

```
app/Domains/
  Shared/
    Enums/             ← PaymentStatus, EnrollmentStatus, ContactType
    ValueObjects/      ← Money, Contact (phone/email normalization)
    DTO/
    Support/           ← IdempotencyKey helper

  Identity/
    Actions/           ← ResolveUserByContactAction
    Services/
    DTO/
    Events/

  Auth/
    Actions/           ← RequestOtpAction, VerifyOtpAction
    Services/
    Contracts/         ← OtpSenderInterface (SMS or Email)

  Enrollment/
    Actions/           ← StartRegistrationAction, ValidateAgeRuleAction,
    Services/          ←   SubmitRegistrationAction, EnrollIfFreeAction,
    DTO/               ←   CreateEnrollmentPaymentAction
    Events/            ← EnrollmentActivated
    Listeners/         ← ActivateEnrollmentOnPaymentConfirmedListener
    Support/           ← AgeRules, GuardianRules, EnrollmentStateMachine

  Payments/
    Actions/           ← CreatePaymentForCheckoutAction, StartPaymentRedirectAction,
    Services/          ←   HandleBmlWebhookAction, HandlePaymentReturnAction
    Gateways/          ← BmlGateway (implements PaymentGatewayInterface)
    Contracts/         ← PaymentGatewayInterface
    DTO/               ← InitiatePaymentDTO, VerifiedCallbackDTO, GatewayStatusDTO
    Events/            ← PaymentConfirmed
    Support/           ← PaymentStateMachine, WebhookIdempotency

  Courses/
    Actions/
    Queries/
    DTO/

  PublicSite/
    Actions/
    Queries/
    DTO/

  Content/             ← CMS: pages, news, articles
    Actions/
    Queries/
    Services/

  Portal/              ← Student dashboard (enrollments, payments, certificates)
    Actions/
    Queries/
    DTO/

  Notifications/
    Actions/
    Services/
    Channels/          ← FCM, database, email
    Contracts/

  Messaging/           ← SMS API v2
    Actions/
    Services/
    Contracts/

  Academics/           ← Admin school ops
    Students/
    Teachers/
    QuranProgress/
    Announcements/
    ELearning/
    Substitutions/

  Admin/
    Analytics/
    Settings/
    UserManagement/
```

---

## Provider Wiring Rules

### `App\Providers\DomainsServiceProvider`
- Registered in `bootstrap/providers.php`.
- Binds every interface → concrete implementation. Example:
  ```php
  $this->app->bind(PaymentGatewayInterface::class, BmlGateway::class);
  $this->app->bind(OtpSenderInterface::class, fn ($app) =>
      new CompoundOtpSender($app->make(SmsOtpSender::class), $app->make(EmailOtpSender::class))
  );
  ```
- No scattered `app()->bind()` calls anywhere else.

### `App\Providers\DomainsEventServiceProvider`
- Registered in `bootstrap/providers.php`.
- Declares **all** `$listen` mappings in one place. No `Event::listen()` calls scattered in controllers or services.
  ```php
  protected $listen = [
      PaymentConfirmed::class => [
          ActivateEnrollmentOnPaymentConfirmedListener::class,
      ],
  ];
  ```
- All listeners run `afterCommit` where they touch the database.

---

## Payment Gateway Contract

### `PaymentGatewayInterface`
```php
initiate(Payment $payment): InitiatePaymentDTO        // returns redirect URL
verifyWebhook(array $payload, array $headers): VerifiedCallbackDTO
queryStatus(Payment $payment): GatewayStatusDTO
```

### BML-specific rules
| Rule | Detail |
|------|--------|
| Webhook is source of truth | `POST /webhooks/bml` is the only place a payment is confirmed |
| Return URL never confirms | `GET /payments/bml/return` only reads current payment status and renders a page |
| Amount/currency mismatch | Log and reject — treat as suspicious, never confirm |
| Transitions are idempotent | `confirmed → confirmed` is a no-op; no duplicate enrollment or charges |
| Stable URLs | Both URLs must be outside locale-prefixed route groups and must not require session |

---

## Implementation Phases

### Phase 0 — Audit First (No Code Changes)
**Goal:** Fully understand the codebase before touching anything.

- [ ] Create `docs/ARCHITECTURE_AUDIT.md` containing:

  **Route catalog** — cover all four route files:
  `routes/web.php`, `routes/web_public.php`, `routes/web_localized.php`, `routes/api.php`

  Each entry must have these fields:
  | Field | Description |
  |-------|-------------|
  | `path` | Full URI pattern |
  | `method` | HTTP verb(s) |
  | `name` | Named route identifier |
  | `middleware` | All middleware applied |
  | `controller@method` | Fully qualified class and method |

  **Module map** — infer logical modules from routes (Enrollment, Payments, Portal, Admin, PublicSite, API, etc.)

  **Critical flows — document each one end-to-end:**
  - **A)** OTP-first auth / account resolver (new vs existing user by contact)
  - **B)** Course registration: parent vs adult 18+ rule, stub account creation, profile completion
  - **C)** Payment flow: checkout → BML redirect → return page → webhook confirmation
  - **D)** Enrollment activation after payment confirmed
  - **E)** Portal: enrollments, payments, certificates, profile
  - **F)** Admin dashboard modules: students, teachers, quran-progress, announcements, e-learning, substitutions, reports, settings
  - **G)** Notifications APIs: mark-read endpoint, stats endpoint
  - **H)** SMS API v2 send endpoint with API-key middleware

  **Coupling hotspots** — controllers doing too many things, services calling other services directly across domain lines

  **Risk list** — ranked by blast radius if refactored incorrectly

  **Recommended refactor order** — based on risk and dependency graph

- [ ] Identify all morph relationships (`morphTo`/`morphMany`) and record which class names are stored in the DB
- [ ] Map all current `Event::listen()` and `EventServiceProvider::$listen` bindings

---

### Phase 1 — Safety Net (Contract Tests Before Refactoring)
**Goal:** Baseline tests that lock in the observable behaviour of every critical flow.
These are **contract tests** — they assert that status codes, redirect targets, and critical JSON keys/shapes remain unchanged throughout the refactor.

- [ ] OTP flow: `can_request_otp` (mock SMS + email senders), `can_verify_otp_success`, `can_reject_invalid_otp`
- [ ] Age rule: adult 18+ enrolls directly, under-18 requires guardian — assert correct redirect/validation response
- [ ] Free course: enrollment record created immediately after OTP verify
- [ ] Paid course: `Payment` record created with `enrollment_pending_payload`; response shape contains BML redirect URL; no `RegistrationStudent` or `CourseEnrollment` exists yet
- [ ] BML webhook idempotency: fire the same webhook payload twice → `RegistrationStudent` + `CourseEnrollment` exist exactly once; second call returns the same success response shape
- [ ] BML return URL: accessible without session or locale prefix; returns HTTP 200
- [ ] Portal routes: unauthenticated request returns 302 redirect to login
- [ ] Notifications API: mark-read returns expected JSON shape; stats endpoint returns expected keys

Use `PaymentGatewayInterface` bound to a `FakeGateway` in the test environment.
Mock SMS and email channels so no real messages are dispatched.

---

### Phase 2 — Formatter + CI *(allowed addition — zero runtime behaviour change)*
**Install code formatting and CI. Must not change any runtime logic.**

- [ ] Install Laravel Pint: `composer require laravel/pint --dev`
- [ ] Add to `composer.json` scripts:
  ```json
  "format": "pint",
  "test": "php artisan test"
  ```
- [ ] Add GitHub Actions workflow `.github/workflows/ci.yml`:
  - `composer install --no-interaction`
  - `php artisan test`
  - `pint --test`
- [ ] Run Pint and commit the formatting changes alone — no logic changes in this commit

---

### Phase 3 — Domain Skeleton + Providers
**No business logic moved yet — create the structure and wire the providers.**

- [ ] Create all `app/Domains/` folders with `.gitkeep`
- [ ] Create `App\Providers\DomainsServiceProvider` (see Provider Wiring Rules above)
- [ ] Create `App\Providers\DomainsEventServiceProvider` (see Provider Wiring Rules above)
- [ ] Register both providers in `bootstrap/providers.php`
- [ ] Add `Shared/` primitives (logic copied, originals kept until replaced):
  - `Money` value object
  - `Contact` value object (normalizes phone and email — source: `ContactNormalizer`)
  - `PaymentStatus` enum
  - `EnrollmentStatus` enum
  - `ContactType` enum
  - `IdempotencyKey` support helper
- [ ] Run full test suite — all tests must pass before proceeding

---

### Phase 4 — Payments Domain *(Highest risk — commit each sub-step separately)*
**Goal:** `Payments` domain owns ALL payment state transitions. Webhook is the only confirmation source.

- [ ] Create `Payments/Contracts/PaymentGatewayInterface` (see contract above)
- [ ] Create `Payments/Gateways/BmlGateway` implementing the interface
  - Port logic from `BmlPaymentProvider` and `BmlConnectService`
  - Keep same `config/bml.php` keys unchanged
  - Keep same return URL and webhook URL unchanged
  - Enforce amount/currency validation — mismatch logs and rejects
- [ ] Create `Payments/Support/PaymentStateMachine`:
  - Valid transitions: `created → initiated → pending → confirmed`
  - Failure paths: `initiated/pending → failed/expired/cancelled`
  - Idempotent: repeated transition to same state is a no-op, not an exception
- [ ] Create `Payments/Support/WebhookIdempotency`:
  - Store `payload_hash`, `signature_valid`, `external_reference` in a `webhook_logs` table
  - Return the same success response for duplicate webhook calls; do not re-process
- [ ] Create `PaymentConfirmed` event (carries `Payment $payment`)
- [ ] Create `HandleBmlWebhookAction` — moves all webhook logic here; controller calls action only
- [ ] Bind `PaymentGatewayInterface` → `BmlGateway` in `DomainsServiceProvider`
- [ ] Run full test suite — all contract tests must pass

---

### Phase 5 — Enrollment Activation via Event
**Goal:** Enrollment creation is triggered by `PaymentConfirmed` event, never called directly from payment code.

- [ ] Create `Enrollment/Listeners/ActivateEnrollmentOnPaymentConfirmedListener`:
  - Reads `enrollment_pending_payload` from `Payment`
  - Calls `EnrollmentService::createEnrollmentForConfirmedPayment()`
  - Idempotent: checks for existing enrollment before creating; no duplicate records
  - Marked `afterCommit`
- [ ] Register listener in `DomainsEventServiceProvider`
- [ ] Remove direct `EnrollmentService` calls from `PaymentService::applyVerifiedResult()` and `finalizeByReference()`; replace with `event(new PaymentConfirmed(...))`
- [ ] Remove direct enrollment creation from `PaymentController` return handler; same replacement
- [ ] Run idempotency test: fire `PaymentConfirmed` twice → exactly one `RegistrationStudent`, one `CourseEnrollment`
- [ ] Run full test suite — all contract tests must pass

---

### Phase 6 — Course Registration into Domain Actions
**Goal:** `CourseRegistrationController` becomes a thin orchestrator. This is the most complex phase — commit each action extraction separately.**

**Auth domain actions:**
- [ ] `RequestOtpAction` — extracted from `OtpService::send()` + `sendForNewRegistration()`
- [ ] `VerifyOtpAction` — extracted from `OtpService::verify()` + `verifyForNewRegistration()`
- [ ] `ResolveUserByContactAction` — extracted from `AccountResolverService`

**Enrollment domain actions:**
- [ ] `ValidateAgeRuleAction` — 18+ rule for adult self-enrollment
- [ ] `StartRegistrationAction` — validates form, stores `reg_pending_data` in session
- [ ] `SubmitRegistrationAction` — creates user account after OTP verified
- [ ] `CreateEnrollmentPaymentAction` — creates deferred `Payment` with `enrollment_pending_payload`; no enrollment records yet
- [ ] `EnrollIfFreeAction` — immediate `RegistrationStudent` + `CourseEnrollment` for zero-fee courses

Each controller method calls at most one or two actions. No Eloquent queries or business logic remain in the controller.

- [ ] Run full test suite — all contract tests must pass

---

### Phase 7 — Public Site, CMS, Portal, Admin Cleanup
**Goal:** No DB queries or business logic in route closures or controller methods.**

- [ ] Move any remaining route closures to named controller methods
- [ ] `PublicSite/Queries/` — news, events, gallery listing queries extracted from controllers
- [ ] Locale set only via `SetLocale` middleware — no manual `App::setLocale()` in controllers
- [ ] `Portal/Queries/` — `GetEnrollmentsQuery`, `GetPaymentHistoryQuery`, `GetCertificatesQuery`
- [ ] `Admin/Actions/` — one action per admin operation (approve enrollment, generate report, etc.)
- [ ] `Content/Queries/` — pages, articles queries extracted
- [ ] Run full test suite — all contract tests must pass

---

### Phase 8 — Notifications + SMS API v2
**Goal:** Notifications and messaging have clean contracts; SMS v2 middleware behaviour is unchanged.**

- [ ] Create `OtpSenderInterface` with separate `SmsOtpSender` and `EmailOtpSender` implementations
- [ ] Create `Messaging/Actions/SendSmsAction` wrapping existing `SmsGatewayService`
- [ ] Create channel contracts for FCM, database, and email notifications
- [ ] SMS API v2 endpoint path, API-key middleware, and response shape must remain identical
- [ ] Run full test suite — all contract tests must pass

---

### Phase 9 — Docs + Release
**Goal:** The codebase is fully refactored and documented.**

- [ ] Update `README.md` to include:
  - Domain map (one line per domain, what it owns)
  - Critical flows A–H (brief summary + entry point file for each)
  - Full list of required env vars (keys only, no values)
  - How to run tests: `php artisan test`
  - How to run formatting: `vendor/bin/pint`
- [ ] Remove dead code and unused services discovered during the refactor
- [ ] Run final Pint pass and commit formatting only
- [ ] Run full test suite — all tests green
- [ ] Tag release: `git tag v2.0.0-modular`

---

## Key Rules Reference

| Rule | Why |
|------|-----|
| Models stay in `App\Models` | Morph class names are stored in the DB — moving them breaks queries |
| Controllers call Actions only | Thin controllers are easy to test and easy to replace |
| Cross-domain = Events or Contracts | Prevents hidden coupling across domain boundaries |
| Webhook is the only confirmation source | Return URL is a display page, not a confirmation |
| State transitions are idempotent | BML can fire webhooks more than once |
| One action = one use-case | Single-responsibility makes testing and changes straightforward |
| Preserve route paths and payload shapes | Changing them silently breaks clients, emails, and bookmarks |
| Safe commits | Every commit must leave the repo runnable and tests passing |

---

## Files That Will Change the Most

- `app/Http/Controllers/CourseRegistrationController.php` — largest, extracted into 7+ actions
- `app/Services/Payment/PaymentService.php` — replaced by domain actions
- `app/Services/Enrollment/EnrollmentService.php` — split into domain actions + listener
- `app/Services/OtpService.php` — replaced by Auth domain actions
- `routes/web_public.php` + `routes/web_localized.php` — closures removed

## URLs That Must Never Change

| URL | Why |
|-----|-----|
| `GET /payments/bml/return` | Linked in BML portal config; users land here after payment |
| `POST /webhooks/bml` | Registered in BML portal; source of payment confirmation |
| `POST /payments/bml/callback` | Alternative callback path; may be registered externally |
| All enrollment route paths | Referenced in confirmation emails sent to students |

---

## NOW START — Execution Checklist

Work through these in order. Do not proceed to the next step until the current step is committed and tests pass.

1. - [ ] Write `docs/ARCHITECTURE_AUDIT.md` (Phase 0)
2. - [ ] Add baseline contract tests (Phase 1)
3. - [ ] Set up Pint + CI (Phase 2)
4. - [ ] Create `app/Domains/` skeleton + providers (Phase 3)
5. - [ ] Refactor Payments domain with idempotent state machine and webhook guard (Phase 4)
6. - [ ] Decouple enrollment activation via `PaymentConfirmed` event (Phase 5)
7. - [ ] Refactor course registration flow into domain actions (Phase 6)
8. - [ ] Refactor public site, CMS, portal, and admin into actions gradually (Phase 7)
9. - [ ] Refactor notifications + SMS API v2 (Phase 8)
10. - [ ] Update docs, ensure all tests pass, tag release (Phase 9)
