# Modular Monolith — Implementation Guide

> **Status:** Not started. Do this AFTER the site goes live and is stable.
>
> **Why wait?** The current codebase works. All critical flows (OTP, BML payments, enrollment,
> deferred enrollment) are tested and running. Refactoring before launch risks breaking things
> that already work. Go live first, then come back here.

---

## What We're Building

Convert the current flat Laravel structure into a **Modular Monolith** under `app/Domains/`.
One app, one database — just much better internal organisation so changes in payments
don't accidentally break enrollment, and changes in OTP don't break the public site.

---

## Non-Negotiables (Do NOT Break These)

1. OTP send/verify and the login/registration flow
2. Course registration (adult 18+ self-enroll vs parent/guardian)
3. Payment flow: checkout → BML redirect → return URL → webhook confirmation
4. Deferred enrollment: `RegistrationStudent` + `CourseEnrollment` created **only after** BML confirms payment
5. BML webhook + return URLs must stay **outside** locale prefix routes (stable URLs)
6. Do NOT move Eloquent models out of `App\Models` — morph strings in the DB depend on class names
7. Keep `.env` out of git — only `.env.example`

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

## Implementation Phases

### Phase 0 — Audit First (No Code Changes)
**Goal:** Understand the full codebase before touching anything.

- [ ] Create `docs/ARCHITECTURE_AUDIT.md` with:
  - Full route catalog (path, method, name, middleware, controller@method)
  - Module map inferred from routes
  - Coupling hotspots (controllers doing too many things)
  - Risk list and recommended refactor order
- [ ] Identify all morph relationships in the database before moving any model
- [ ] Map all Event/Listener bindings currently scattered across providers

---

### Phase 1 — Safety Net (Tests Before Refactoring)
**Goal:** Make sure nothing breaks silently during the refactor.

Add feature tests for:
- [ ] OTP: `can_request_otp`, `can_verify_otp` (mock SMS/email)
- [ ] Enrollment: adult age rule blocks under-18, guardian flow works
- [ ] Free course: enrollment activated immediately
- [ ] Paid course: creates Payment record with `enrollment_pending_payload`, returns BML redirect
- [ ] Webhook: `POST /webhooks/bml` confirms payment and creates enrollment exactly once (idempotency)
- [ ] Portal routes: require authentication
- [ ] BML return URL: accessible without session/locale

Use dependency injection to bind `PaymentGatewayInterface` → `FakeGateway` in tests.

---

### Phase 2 — Formatter + CI
**Low risk. Do this first.**

- [ ] Install Laravel Pint: `composer require laravel/pint --dev`
- [ ] Add to `composer.json` scripts:
  ```json
  "format": "pint",
  "test": "php artisan test"
  ```
- [ ] Add GitHub Actions workflow (`.github/workflows/ci.yml`):
  - `composer install`
  - `php artisan test`
  - `pint --test`
- [ ] Run Pint and commit formatting only (zero logic changes)

---

### Phase 3 — Create Domain Skeleton
**No logic moved yet — just create the folder structure and providers.**

- [ ] Create all `app/Domains/` folders with `.gitkeep`
- [ ] Create `App\Providers\DomainsServiceProvider`
  - Registers interface → implementation bindings
- [ ] Create `App\Providers\DomainsEventServiceProvider`
  - Declares all `$listen` mappings (no scattered `Event::listen()`)
- [ ] Register both providers in `bootstrap/providers.php`
- [ ] Add `Shared/` primitives:
  - `Money` value object
  - `Contact` value object (phone/email normalization — move from `ContactNormalizer`)
  - `PaymentStatus` enum
  - `EnrollmentStatus` enum

---

### Phase 4 — Payments Domain (Highest Risk — Do Carefully)
**Goal:** Payments domain owns ALL payment state transitions. Webhook is the single source of truth.**

- [ ] Create `Payments/Contracts/PaymentGatewayInterface`:
  ```php
  initiate(Payment $payment): InitiatePaymentDTO
  verifyWebhook(array $payload, array $headers): VerifiedCallbackDTO
  queryStatus(Payment $payment): GatewayStatusDTO
  ```
- [ ] Create `Payments/Gateways/BmlGateway` implementing the interface
  - Port logic from existing `BmlPaymentProvider` and `BmlConnectService`
  - Keep same config keys (`config/bml.php`)
  - Keep same return/webhook URLs
- [ ] Create `Payments/Support/PaymentStateMachine`:
  - `created → initiated → pending → confirmed`
  - `initiated/pending → failed/expired/canceled`
  - Idempotent: repeated transitions to same state are no-ops
- [ ] Create `Payments/Support/WebhookIdempotency`:
  - Store payload hash + `signature_valid` + `external_reference` in a `webhook_logs` table
  - Reject duplicate webhook calls
- [ ] Create `PaymentConfirmed` event
- [ ] Move webhook handling into `HandleBmlWebhookAction`
- [ ] Bind `PaymentGatewayInterface` → `BmlGateway` in `DomainsServiceProvider`
- [ ] Run existing tests to verify nothing broke

---

### Phase 5 — Enrollment Activation via Event
**Goal:** Enrollment activation is triggered by `PaymentConfirmed` event, not directly in controllers.**

- [ ] Create `Enrollment/Listeners/ActivateEnrollmentOnPaymentConfirmedListener`
  - Reads `enrollment_pending_payload` from Payment
  - Calls existing `EnrollmentService::createEnrollmentForConfirmedPayment()`
  - Idempotent: checks if enrollment already exists before creating
  - Runs `afterCommit`
- [ ] Register listener in `DomainsEventServiceProvider`
- [ ] Remove direct enrollment-creation calls from `PaymentService::applyVerifiedResult()` and `finalizeByReference()`
  - Replace with `event(new PaymentConfirmed(...))`
- [ ] Run idempotency test: fire `PaymentConfirmed` twice → enrollment created only once

---

### Phase 6 — Course Registration Untangling
**Goal:** `CourseRegistrationController` becomes a thin wrapper.**
**This is the most complex phase — take it slowly.**

Extract into Actions:

**Auth domain:**
- [ ] `RequestOtpAction` — from `OtpService::send()` + `sendForNewRegistration()`
- [ ] `VerifyOtpAction` — from `OtpService::verify()` + `verifyForNewRegistration()`
- [ ] `ResolveUserByContactAction` — from `AccountResolverService`

**Enrollment domain:**
- [ ] `ValidateAgeRuleAction` — 18+ check for adult self-enrollment
- [ ] `StartRegistrationAction` — validates form, stores `reg_pending_data` in session
- [ ] `SubmitRegistrationAction` — creates user account after OTP verified
- [ ] `CreateEnrollmentPaymentAction` — creates deferred `Payment` with `enrollment_pending_payload`
- [ ] `EnrollIfFreeAction` — immediate enrollment for zero-fee courses

Controllers call actions only. One action per use-case.

---

### Phase 7 — Public Site, Portal, Admin Cleanup
**Goal:** No DB queries or business logic in route files or route closures.**

- [ ] Move any remaining route closures to controllers
- [ ] `PublicSite/Queries/` for news/events/gallery listing queries
- [ ] Ensure locale is set via `SetLocale` middleware, not manually in controllers
- [ ] Portal actions: `GetEnrollmentsQuery`, `GetPaymentHistoryQuery`
- [ ] Admin actions: one Action per admin operation (approve enrollment, etc.)

---

### Phase 8 — Notifications + SMS v2
- [ ] Create `OtpSenderInterface` with SMS and Email implementations
- [ ] `Messaging/Actions/SendSmsAction` wraps existing `SmsGatewayService`
- [ ] Create channel contracts for FCM, database notifications
- [ ] Keep SMS API v2 middleware behavior identical

---

### Phase 9 — Final Cleanup + Docs
- [ ] Update `README.md` with domain map, critical flows, env vars
- [ ] Remove any dead code found during refactor
- [ ] Run full test suite
- [ ] Run Pint formatting pass
- [ ] Tag a release: `v2.0.0-modular`

---

## Key Rules to Remember

| Rule | Why |
|------|-----|
| Models stay in `App\Models` | Morph strings in DB reference class names |
| Controllers call Actions only | Thin controllers, testable logic |
| Cross-domain = Events or Contracts | No direct service-to-service coupling |
| Webhook is source of truth | Return URL never confirms payment |
| One action = one use-case | Easy to test, easy to change |
| Idempotency everywhere in payments | BML can fire webhooks multiple times |

---

## Files That Will Change the Most

- `app/Http/Controllers/CourseRegistrationController.php` — biggest, most complex
- `app/Services/Payment/PaymentService.php` — split into domain actions
- `app/Services/Enrollment/EnrollmentService.php` — split into domain actions
- `app/Services/OtpService.php` — moves to Auth domain
- `routes/web_public.php` + `routes/web_localized.php` — cleaned up

## Files That Must NOT Change Externally

- BML return URL: `GET /payments/bml/return`
- BML webhook URL: `POST /webhooks/bml`
- BML callback URL: `POST /payments/bml/callback`
- All enrollment route paths (users have bookmarks/emails with these)
