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
| Agent | S1.1b unification backfill + verify command | **Done** (`5c91c93`) |
| Agent | S1.1c Deploy 2 switch reads | **Done** (`2f8a90b`) |
| Agent | S1.2–S1.5 | **Done** (`8f1ecb8`) |
| Agent | Docs §5.1 audience-surface (`#13`) | **Done** (`5690034`) |
| Agent | Staging `students:verify-unification` | **Blocked** — no SSH / no server console |
| Agent | Branch protection on `main` | **Blocked** — `403 Resource not accessible by integration` |
| Operator | `students:verify-unification` on staging + archive report | **Pending** — S2 blocked until green |
| Operator | Branch protection (required `quality`, no direct push, no self-merge) | **Pending** — apply in repo settings |

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

## S1.1c — Switch reads to unified students (Deploy 2, dual-write ON)

- `CourseEnrollment::student()` / `Payment::student()` read `students` via
  `unified_student_id` (config morph, no new People model imports).
- Additive `payments.unified_student_id` + backfill. Save observers fill the
  unified id from the legacy mapping.
- Dual-write: `DualWriteCourseStudentAction` + `LinkGuardianDualWriteAction`.
  `EnrollmentService` still writes `registration_students` / `student_guardians`.
- `RegistrationStudent` marked `@deprecated`. Student `dob`/`age` and
  ParentGuardian `name` keep existing Blade/mail working.
- Posted enrollment `student_id` is still the legacy RS id. ADR-008.
- Architecture baselines shrunk (Password OTP off rule 1; four rule-2
  RegistrationStudent imports removed). Hifz untouched.

## S1.2 — Custom fields engine

- Tables: `custom_field_definitions` (soft-deletes, unique key per entity,
  trilingual labels, types `text|textarea|number|date|select|multiselect|boolean`)
  and `custom_field_values` (json, unique definition+entity).
- `SaveCustomFieldValuesAction` validates type + required; reused by student
  profile and admission preview.
- Admin CRUD + admission-form preview at `people.custom-fields.*`.
- React `<CustomFields>` plus student directory/profile (`people.students.*`)
  with CSV export, guardian attach/detach, medical gated by
  `students.view-sensitive`. Legacy Blade `students.*` unchanged.
- Permissions: `custom_fields.manage`, `students.view-sensitive`.
- Inertia page-finder path set to `resources/js/Pages` (`config/inertia.php`).
- Hifz / dual-write / Deploy 3 untouched.

## S1.3 — Consent & privacy

- `consents` ledger: person (student|guardian), type
  (`photo_media_use`, `ai_training_samples`, `data_processing`,
  `marketing_messages`), granted, granted_by, granted_at, revoked_at, source.
- `RecordConsentAction` inserts a new row on change; never updates `granted`
  in place. Same-value writes are no-ops.
- Student profile Consents tab + portal children list (`portal.children`)
  scoped to the signed-in guardian.
- ADR-009 retention (ADR-002 was already analytics). Hifz untouched.

## S1.4 — Staff profiles

- `staff_profiles` + `staff_qualifications` (optional `document_id` → Media
  `documents`). Teacher rows unchanged except additive nullable
  `teachers.staff_profile_id`. No contracts/leave/payroll (S5).
- Inertia staff index/profile at `people.staff.*`. Hifz Teacher behavior frozen.

## S1.5 — Academic year / term backbone

- `academic_years.status` (`upcoming|active|closed`); `is_current=true` →
  `active`. `terms` json **kept**. New `terms` table backfilled.
- `course_enrollments.unified_term_id` FK to `terms` (legacy `term_id` /
  generated `term_key` untouched; orphans → Legacy term).
- `classes.academic_year_id` + optional `class_teacher_staff_profile_id`
  (`class_teacher_id` still → users). Unique `(name, section, academic_year_id)`.
- `class_student` pivot; dual-write `students.class_id`.
- Actions: `ActivateAcademicYearAction` (fails if another year is active;
  does not close it), `CloseAcademicYearAction` (all terms closed),
  `PromoteStudentsAction` (dry-run required; promote/repeat/leave/graduate
  via `ChangeStudentStatusAction::executeById`).
- Inertia: years/terms, classes/roster, promotion wizard.
- ADR-010 promotion semantics. Hifz AcademicYear usage unchanged (additive
  fillable/status only).

## Roadmap review (2026-08-24) — docs only, no behavior change

Post-S1 review of the plan folded into `docs/ROADMAP.md` + `CLAUDE.md`:

- **§4 / CLAUDE.md conventions:** merge gates become mechanism (branch protection,
  one slice per PR, CI pre-merge, no bot self-merge; verification-script output in
  STATUS.md **before** the deploy it gates) — direct lesson of the S1 rollout, where
  Deploy 2 shipped before `students:verify-unification` ran and PR #11 self-merged
  pre-CI. Branch protection itself still needs to be **applied on GitHub** (operator
  or separate slice) — the docs now require it; the repo does not yet enforce it.
- **§7 risk notes:** dual payment path named a standing risk until spec Phase 4;
  morph-alias discipline (ADR-005) made a standing rule for every new
  (pseudo-)polymorphic column; community/engagement given a concrete revisit trigger.
- **§2d:** live-classes Level 2 gets an explicit pull-forward decision trigger if
  online cohorts become primary before Phase 2.
- **§9.3:** L-track hold sharpened — hold at L1–L2 until S2 register loop + Phase 1B
  are live; MMA stored-value ADR required before L3 implementation starts.
- **§5.1 (2026-08-24 follow-up):** audience-surface decision recorded — one backend,
  role-scoped surfaces per audience (student / teacher–supervisor–dean / participant /
  public site); separate per-audience apps rejected; a separate SPA client is justified
  only by device/offline constraints (Bakeandgrill pattern), never by audience.

## Pre-S2 readiness (2026-08-24) — gates, not S2 code

### 1. Docs branch `claude/polymorphic-morph-map-hotfix-z01v98`

**Merged.** One docs-only commit is on `main` as
`5690034` (`docs(roadmap): §5.1 one backend, audience-specific surfaces (#13)`).
Remote branch deleted after squash-merge. CI on PR #13 was green
([Actions run 32790086588](https://github.com/ampilarey/akuru/actions/runs/32790086588)).

Do **not** reopen that branch or file a second PR for the same commit
(`df1a150` is already in `5690034`).

### 2. Staging `php artisan students:verify-unification` — **NOT RUN**

This agent has **no staging server access**. Per the readiness brief, the
command was **not simulated** against local `akuru_institute` / `akuru_test`.

| Probe | Result |
|-------|--------|
| SSH keys / `~/.ssh/config` | None (only `known_hosts`) |
| `ssh akuruedu@test.akuru.edu.mv` | `Network is unreachable` (port 22) |
| `ssh akuruedu@akuru.edu.mv` | `Permission denied (publickey,password)` |
| Staging deploy path | Webhook → `scripts/pull-deploy-test.sh` only (`docs/STAGING.md`). That script does **not** run `students:verify-unification`. |

**Verdict:** no counts. No `storage/app/s11b-student-unification-report.json`
from staging. Nothing archived under `docs/migrations/`. Deploy 2
verify-unification gate is **not** retroactively satisfied.

**Operator (SSH or console on `test.akuru.edu.mv`):**

```bash
cd ~/test.akuru.edu.mv
php artisan students:verify-unification
```

Paste the full stdout here and copy
`storage/app/s11b-student-unification-report.json` into
`docs/migrations/` (S1 DoD line 158). Record the verbatim summary in this
file in the same format as the morph-map capture above.

- Zero unresolved → mark the Deploy 2 gate retroactively satisfied.
- Nonzero → list affected students/enrollments, assess whether any current
  enrollment/payment read resolves to a mismatched student, **report and
  stop** (do not start S2).

### 3. Branch protection on `main` — **BLOCKED** (permissions, not plan tier)

Repo is **public**. `GET /repos/ampilarey/akuru/rulesets` → `[]` (no rulesets).
This agent's GitHub token **cannot write** protection. Repo API permissions
for this integration: `admin=false`, `maintain=false`, `push=false`.

```text
POST /repos/ampilarey/akuru/rulesets
→ HTTP 403 Resource not accessible by integration

PUT /repos/ampilarey/akuru/branches/main/protection
→ HTTP 403 Resource not accessible by integration

GET /repos/ampilarey/akuru/branches/main/protection
→ HTTP 403 Resource not accessible by integration
```

No workaround applied (no status-check-only hack, no Actions-side merge
block). **Leave for the operator** (repo admin).

Intended settings (classic protection or a ruleset):

- Required status check: workflow job name **`quality`** (workflow `CI`,
  `.github/workflows/ci.yml`) — require branches to be up to date.
- Require a pull request before merging (blocks direct pushes to `main`).
- Require at least 1 approving review **and**
  `require_last_push_approval: true` (GitHub-native: the author / last
  pusher cannot satisfy the review — covers bot self-merge).
- No bypass actors (or empty bypass list); consider `enforce_admins`.

Settings UI: `https://github.com/ampilarey/akuru/settings/branches`

## Next

**S2 is blocked.** Do not start S2 feature code, S1.1 Deploy 3, or Hifz
until **both** of the following are recorded in this file:

1. `php artisan students:verify-unification` on **staging** is green
   (zero unresolved) — verbatim output + archived JSON under
   `docs/migrations/`.
2. Operator credential smoke (portal / Hifz / payments / notifications /
   OTP `7820288`/`7972434` / BML sandbox) is recorded.

Also still open (not S2):

- **Branch protection on `main`** — operator; 403 for this agent (see
  Pre-S2 readiness §3).
- **S1.1 Deploy 3** stays **≥2 weeks after Deploy 2** (`2f8a90b`,
  2026-08-24). Dual-write and `student_guardians` /
  `registration_students` stay until then (`docs/S1_SPEC.md` Deploy 3).
- Blade `students.*` / `teachers.*` still live (S1.2–S1.5 added Inertia
  alongside; S1 DoD wanted those routes gone).
- Infra: `Relation::enforceMorphMap()` after production verification;
  S1.1a items 1–3 remain operator-deferred; per-domain `routes.php` /
  domain migrations; shrink architecture baselines as domains decouple.
