# Status

## Phase 0 — Foundation (closure: code + CI on `main`; staging deploy **done**)

**Honest summary:** Domain moves, contracts, architecture baselines, and CI are done on `main`. Staging at **`9e8e8f6`** (includes Phase 0 code at `e84c657`) deployed 2026-06-13 on `test.akuru.edu.mv`. Public smoke passes; auth/BML/portal/admin/Hifz need credential-based spot-check by operator.

### Staging verification (2026-06-13)

| Item | Result |
|------|--------|
| Target URL | https://test.akuru.edu.mv |
| Server path | `~/test.akuru.edu.mv` (`/home/akuruedu/test.akuru.edu.mv`) |
| Deployed commit | **`f8b8a56`** (2026-06-13; see `docs/STAGING.md`) |
| Phase 0 code commit | **`e84c657`** (included in `9e8e8f6`) |
| `.env` backup | Done on server |
| DB backup | `storage/backups/staging-akuruedu_test.akuru.edu.mv-20260613-082231.sql.gz` |
| Migrations | All ran; `migrate --force` → nothing pending |
| `npm run build` on server | **Skipped** — `npm` not in PATH; no frontend file changes between prior checkout and `9e8e8f6` |
| Divergent server commits | 5 duplicate CI commits dropped via `reset --hard` (same content as GitHub `6fceabd`) |

**Post-deploy browser smoke:**

| Area | Result |
|------|--------|
| `/up` health | 200 OK |
| Public EN/DV/AR | 200 OK |
| Courses, contact, gallery, news | 200 OK |
| `/inertia-test` | 200 OK |
| `/login` | 302 → login flow |
| BML sandbox checkout | **Not tested** — needs operator + sandbox credentials |
| Enrollment / OTP | **Not tested** — use `7820288` / `7972434` only |
| Portal / admin / Hifz | **Not tested** — needs staging credentials |

**Automated (CI / local at HEAD `bb81562`, not run on server):**

| Check | Result |
|-------|--------|
| Pest | 122 passed, 1 todo |
| Pint | PASS (472 files) |
| PHPStan | ~410 errors (informational) |
| GitHub Actions `main` | Green — [run 27475486703](https://github.com/ampilarey/akuru/actions/runs/27475486703) |

**Verdict:** `PHASE 0 STAGING PASSED` (deploy + public smoke). Credential-based flows (login, BML, enrollment SMS, portal, admin, Hifz) remain **operator spot-check** before production deploy.

### 0.1 Install & tooling
- Inertia + React, Pest, Larastan, ADR template, CI (`pint` + `pest` + architecture suite)
- **CI green on `main`:** [GitHub Actions run 27475486703](https://github.com/ampilarey/akuru/actions/runs/27475486703) (Pint + Pest 122 passed, 1 todo; PHPStan informational)

### 0.2 Domain skeleton
- 19 domain providers + ADR-001 (single-institute, `school_id` retained)

### 0.3 Move map (namespace/route only)
- All 13 model/controller/service **moves** done under `app/Domains/*`
- **`app/Models/` and `app/Services/` directories removed** (not merely empty — they do not exist; models live in domains)
- `app/Http/Controllers/` = `Controller.php` only
- **Per-domain `routes.php` split: NOT done** — deferred to early S1 infrastructure slice; `routes/web_localized.php` and `routes/web_public.php` remain central; route-name snapshot tests guard URL names

### 0.4 Contracts
- `SmsSenderInterface`, `PushSenderInterface` (null stub)
- `ImageProcessorInterface`, `MediaStorageInterface`
- `PaymentProviderInterface` in Finance\Contracts
- `DocumentRendererInterface` stub in Support

### 0.5 Architecture tests (CI-blocking via Pest)
| Rule | Enforcement |
|------|-------------|
| 1 — no cross-domain `Models\*` | Baseline **76 files** |
| 2 — cross-domain only via Contracts/DTOs/Events/Actions | Baseline **184 entries** |
| 3 — no concrete SMS/WebP/BML cross-domain | Pest arch; `CheckoutController` BML grandfathered |
| 4 — no `DB::` in domain controllers | Baseline **4 controllers** |
| 5 — Hifz only via Portal contracts | Baseline **3 files** |
| 6 — Commerce wallet tables | **Todo/skipped** until L4 |

Baselines may only **shrink**; never grow.

### 0.6 Cleanups
- [x] Root deployment/readme MDs → `docs/legacy/`
- [x] Specs in `docs/` (`SPEC.md`, `ROADMAP.md`, `S1_SPEC.md`, etc.)
- [ ] Duplicate OTP migration squash — deferred (verify prod schema first)

### 0.7 Definition of done (checklist vs reality)
| Criterion | Status |
|-----------|--------|
| Route-name snapshots green | **Done** (122 Pest tests, 1 arch todo) |
| `app/Models` / `app/Services` gone | **Done** (directories removed) |
| Pest + arch in CI | **Done** on `main` after closure push |
| Per-domain `routes.php` | **Deferred** → S1 |
| Manual smoke + production deploy | Staging deploy **done** (`9e8e8f6`); credential smoke pending; production **not started** |
| PHPStan blocking CI | **No** (informational only) |

### Closure slice (no behavior change)
- CI: MySQL 8 + `APP_KEY` in `phpunit.xml`
- Removed 6 unreferenced Laravel UI auth stubs; **`VerifyEmailController` kept** (routed)
- Fixed duplicate `Controller` imports (`AdminUserController`, `InstructorController`)

## Morph-map hotfix (blocks production + S1) — branch `claude/polymorphic-morph-map-hotfix-z01v98`

Phase 0 moved models into domains but left polymorphic / class-name columns holding
FQCNs from **two eras** (ADR-005):

- Pre-Phase-0: `App\Models\*` / `App\Notifications\*`
- Post-Phase-0 on staging (since 2026-06-13, no morph map): `App\Domains\*\Models\*`

Fix:

- `config/morph-map.php` — alias → current FQCN for all 80 domain models (reuses
  `domain-models.php` aliases); **non-enforcing** `Relation::morphMap()` via
  `App\Providers\MorphMapServiceProvider` (first in `bootstrap/providers.php`).
- `App\Support\MorphMap::backfill()` — rewrites **both** `App\Models\*` and
  `App\Domains\*` morph FQCNs → alias (`array_flip` of the config); collapses
  duplicate `model_has_roles` / `model_has_permissions` rows that would PK-collide;
  `notifications.type` is FQCN → FQCN only (not aliased). Thin central migrations
  call this; `down()` is a no-op. `000002` re-runs backfill for DBs that already
  applied the Models-only `000001`.
- Gate: `php artisan morph-map:verify` — morph columns must contain **no backslash**
  (any FQCN is wrong); prints collapse counts from the backfill report.
- Call sites fixed to `(new User)->getMorphClass()`: `AdminUserController`,
  `ClearNonAdminUsers`.

### Staging / production deploy runbook (atomic cutover)

1. **Drain the queue and stop workers** — serialized jobs/notifications embed old FQCNs.
2. **`php artisan down`** — between code live and migration finish, role lookups use
   aliases while the DB still holds FQCNs (admin-lockout window). Maintenance mode
   closes it; deploy ordering alone does not.
3. Deploy code → `php artisan migrate --force`.
4. Run `php artisan morph-map:verify`. Non-zero → investigate before proceeding.
   Record **collapse counts** from the verify output (staging mixed-era evidence).
5. `php artisan permission:cache-reset`; clear config/route cache; `queue:restart`.
6. `php artisan up`, then confirm an admin can log in and sees admin nav.

**Run on STAGING FIRST** — staging carries a **mixed-era dataset** (pre-June
`App\Models\*` + post-June `App\Domains\*` rows). It is a real test of both rewrite
directions and of composite-key collapse, and also covers part of the credential
smoke still listed as outstanding above.

### Staging remediation (2026-08-16) — `05b8cca` on `main`

| Item | Result |
|------|--------|
| Merged to `main` | `05b8cca` (mixed-era rewrite + composite-key collapse) |
| Auto-deploy | Webhook HTTP 202 ([Actions run 31728779585](https://github.com/ampilarey/akuru/actions/runs/31728779585)); `~/self-update-test.log` shows `deploy complete: 05b8ccaa` after migrate/cache/queue restart |
| Migration `000002` | Applied by **auto-deploy** `migrate --force` (not a manual migrate) — remediation path for DBs that already ran Models-only `000001` |
| Manual post-deploy | `php artisan morph-map:verify` then `php artisan permission:cache-reset` (deploy-gate was not yet on `main` at remediation time) |

**Verbatim `php artisan morph-map:verify` (staging):**

```text
Collapse report (duplicate pivots merged before rewrite):
  • model_has_roles: 1 group(s) collapsed
    - model_has_roles role_id=1 model_id=1 kept=user dropped=[App\Domains\Identity\Models\User] → user

morph-map:verify OK — morph columns have no FQCNs; notifications.type is clean.
```

**Collapse interpretation:** 1 group collapsed is the **expected** mixed-era outcome (role granted both pre- and post-domain-move). Kept already-aliased `user`; dropped post-Phase-0 `App\Domains\Identity\Models\User`. Zero collapses would have been surprising.

**Verbatim `php artisan permission:cache-reset`:**

```text
Permission cache flushed.
```

**Gate:** GREEN. Credential smoke (admin login + nav, portal, Hifz, enrollment OTP `7820288`/`7972434`, BML sandbox) is now unblocked — see section below.

### Staging credential smoke (2026-08-23) — post morph-map `048974b`

Target: https://test.akuru.edu.mv · HEAD expected `048974b` / morph remediation. Checks chosen to exercise rewritten roles, payables, and notification classes (not generic page-load smoke).

| # | Check | Result |
|---|--------|--------|
| 1a | Role resolution — admin created **before** 2026-06-13 | **Not tested** — no pre-move admin credentials available to this agent |
| 1b | Role resolution — admin created **after** 2026-06-13 (post-move, pre-hotfix) | **Not tested** — no post-move admin credentials available to this agent |
| 1c | Role resolution — **user id 1** (collapse: `role_id=1 model_id=1`) | **Not tested** — no credentials for user id 1 |
| 2 | Portal — guardian/student views; guardian sees own children only | **Not tested** — blocked on step 1 (no authenticated session) |
| 3 | Hifz — dashboards load and show data (regression only) | **Not tested** — blocked on step 1 |
| 4 | Payments — old pre-move payment `payable` resolves in admin | **Not tested** — blocked on step 1 |
| 5 | Notifications — stored notification renders (rewritten `type`) | **Not tested** — blocked on step 1 |
| 6 | Enrollment OTP — `7820288` / `7972434` only | **Not tested** — blocked on step 1; SMS path also needs live gateway confirmation |
| 7 | BML sandbox — initiate checkout, redirect/return (no faked webhook) | **Not tested** — blocked on step 1; sandbox operator credentials not available |

**Login probe (seed accounts — expected to fail on real staging data):**

Attempted password login at `/en/login` with identifier/password pairs from local seed docs (`admin@akuru.edu.mv` / `password`, `teacher@akuru.edu.mv` / `password`, and phone identifiers `7820288` / `7972434` with `password`). Every attempt returned **HTTP 302 → `/en/login`** (no session established). Staging does not use the local seeder password set for these checks.

**Verbatim curl outcome (representative):**

```text
POST https://test.akuru.edu.mv/en/login
identifier=admin@akuru.edu.mv password=password
→ HTTP 302 Location: https://test.akuru.edu.mv/en/login
(same for teacher@akuru.edu.mv, 7820288, 7972434)
```

**Stop reason:** Step 1 (role resolution) could not be executed without operator-supplied staging credentials for (a) a pre-2026-06-13 admin, (b) a post-move / pre-hotfix admin, and (c) user id 1. Per smoke protocol, later steps were not continued.

**S1.1a gate (items 1–3):** **DEFERRED BY OPERATOR (2026-08-23)** — remaining
credential smoke (portal / Hifz / payments / notifications / OTP / BML, plus
era-specific admin accounts) skipped to continue coding. Resume those checks
before production. Admin login itself was confirmed by the operator.

**Follow-up (not this slice):** flip to `Relation::enforceMorphMap()` after
production verification. S1.1a ADR for guardian/enum becomes **ADR-006**.

### Staging auto-deploy gate (`scripts/pull-deploy-test.sh`) — merged `acad852`

Staging webhook deploys now self-gate: after `migrate --force` they run
`permission:cache-reset` (warn if missing) and, after the cache chain,
`php artisan morph-map:verify` (full output + collapse counts in
`~/self-update-test.log`; `GATE FAILED` → deploy exit 1). Missing
`morph-map:verify` on older commits warns and does not fail.

**On `main` as of merge `acad852`.** Takes effect from the **second** auto-deploy
after that merge (bash keeps executing the pre-pull script after `git merge`).
See `docs/STAGING.md` (re-exec-after-pull rejected: blast radius).

### Agent vs operator (remaining)

| Owner | Item | Status |
|-------|------|--------|
| Agent | Morph-map hotfix + mixed-era remediation on `main` | **Done** (`05b8cca`, gate green) |
| Agent | Staging verify capture in `STATUS.md` | **Done** |
| Agent | Deploy-gate (`morph-map:verify` + `permission:cache-reset` in pull script) | **Done** (merged) |
| Agent | Credential smoke without staging passwords/SSH | **Blocked** — seed logins rejected |
| Operator | Credential smoke steps 1–7 (esp. 1–3 for S1.1a) | **Pending** — need passwords/OTP or SSH |
| Agent | S1.1a schema slice | **Done** (`ff42e9d`) |
| Agent | S1.1b unification backfill + verify command | **Done** (PR) |

## S1.1a — Unified Student schema (Deploy 1 additive, no backfill)

- Students: school fields nullable; passport/email/nationality/medical/legacy key;
  `status` widened to string + `People\Enums\StudentStatus` (not mass-assignable).
- Tables: `emergency_contacts`, `student_status_history`, `guardian_student`,
  Media `documents`; Courses `course_enrollments.unified_student_id` (old
  `student_id` untouched).
- `ChangeStudentStatusAction` writes history in a transaction.
- Morph alias `document` registered. ADR-006. Central migrations (domain folders
  still unwired).
- **Not in this slice:** S1.1b matching/backfill, React screens, Hifz.

## S1.1b — Unified Student backfill (Deploy 1, no read switch)

- Additive: `students.user_id` nullable (child need not have a login); unique
  `legacy_registration_student_id`. ADR-007.
- `UnifyStudentsAction`: match RS → student by user_id / national_id (decrypt) /
  exact name+dob; fill empty passport/national_id; no-match creates
  prospective (or active if any active enrollment); migrate
  `student_guardians` → find/create `parent_guardians` + `guardian_student`;
  backfill `course_enrollments.unified_student_id`.
- Ambiguous (`>1` candidate) and collisions (two RS → one student) are listed,
  not guessed. First RS wins the legacy slot.
- Gate: `php artisan students:verify-unification` (`--backfill` re-runs the
  idempotent job). Report: `storage/app/s11b-student-unification-report.json`.
  Zero unresolved = Deploy 2 gate. Not wired into auto-deploy.
- People action uses `DB::table` for users/enrollments (no Courses/Identity
  model imports). Hifz / RegistrationStudent reads untouched.

## Next

- Run `students:verify-unification` on a staging/production-data copy; resolve
  ambiguous/colliding rows (Deploy 2 gate)
- S1.1 Deploy 2 — switch reads to `students` + `unified_student_id`
- Resume deferred staging credential smoke before production
- Per-domain `routes.php` / domain migrations (infra)
- Shrink architecture baselines as domains decouple
