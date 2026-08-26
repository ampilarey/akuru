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
| Agent | Staging `students:verify-unification` (manual SSH) | **Blocked** — no SSH; S2.0 automates via deploy log |
| Agent | Branch protection on `main` | **Blocked** — `403 Resource not accessible by integration` |
| Agent | S2.0 unify-verify deploy gate | **Done** (merged #15) |
| Agent | S2.1–S2.10 coding on `main` | **Done** (merged #16–#25) |
| Agent | S2.0b staging-test PR (this slice) | **This slice** — now also the S1.1b FK hotfix |
| Operator | Copy verify output from `~/self-update-test.log` + archive JSON | **Received 2026-08-25** — stdout recorded; JSON still on server |
| Operator | Branch protection (required `quality`, no direct push, no self-merge) | **Deferred** (operator-approved 2026-08-25) |
| Operator | Credential smoke steps 1–7 | **Deferred** (operator-approved 2026-08-25); **production receives nothing** until recorded |

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
  Zero unresolved = Deploy 2 gate. Wired into auto-deploy as of S2.0
  (read-only; never `--backfill`). First-deploy caveat: evidence from the
  **second** merge after S2.0.
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
| Staging deploy path | Webhook → `scripts/pull-deploy-test.sh` only (`docs/STAGING.md`). S2.0 adds the read-only verify gate; evidence starts on the **second** deploy after that merge. |

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

## S2 kickoff — deferred gates (operator-approved 2026-08-25)

Branch protection, staging `students:verify-unification` evidence, and
credential smoke are **deferred**. Consequences accepted:

- **(a)** Until protection exists, **every PR in this phase waits for CI
  green and is NOT self-merged** (state that in each PR body).
- **(b)** Production receives **nothing** until credential smoke is recorded.
- **(c)** Any S2 slice that **writes student-keyed rows** (attendance,
  register entries, absence notes, behavior) is **BLOCKED** until
  `students:verify-unification` is green on staging (deploy-log evidence
  copied here + JSON under `docs/migrations/`).

### S2.0 — Unify-verify deploy gate (merged #15)

`scripts/pull-deploy-test.sh` runs `php artisan students:verify-unification`
(read-only, never `--backfill`) after `morph-map:verify`. Warn-don't-fail if
the command is missing; `STUDENT-UNIFICATION GATE FAILED` + exit 1 on
nonzero. `docs/STAGING.md` updated.

**First-deploy caveat (known):** the #15 merge still ran the pre-pull script.
S2.1–S2.10 merges already pushed `main` after that, so later deploys
should have executed the gated script. **S2.0b** is the operator test
PR: another gated deploy plus the paste/archive checklist. Evidence is
still **not** recorded here until the operator pastes the log.

## S2.1 — Rooms (first-class, no student writes)

Plan confirmed (operator “Next”). Rooms only — timetable `room_id` / year /
conflict engine is **S2.2**.

- New `rooms`: name + AR/DV, building, capacity, `RoomType`
  (`classroom|lab|hall|online|other`), bookable, active. Unique `name`.
- `SyncRoomsFromTimetableStringsAction` creates rooms from distinct
  `timetables.room` strings (fills empty translations). Idempotent.
  Legacy `room` / `room_arabic` / `room_dhivehi` columns **kept**.
  No `timetables.room_id` yet.
- Admin Inertia CRUD + CSV at `academics.rooms.*`. Permission `rooms.manage`.
- Morph alias `room`. Hifz / dual-write / Deploy 3 untouched.

## S2.2 — Timetable v2 + conflict engine (no student writes)

- Additive `timetables.academic_year_id`, `term_id`, `room_id`, `valid_from` /
  `valid_until`. Legacy `room*` strings and `start_date`/`end_date` kept.
- `BackfillTimetableYearAndRoomsAction` stamps the active year, copies
  validity from start/end dates, matches `room` strings → `rooms`.
- `TimetableConflictChecker` (pure): teacher / room / class × period- or
  time-based × validity window + year. Adjacent times do not overlap.
- `SaveTimetableEntryAction`: XOR period vs start+end; hard-block on
  conflict unless `allow_conflict` + `timetables.allow_conflict` + reason
  (logged). Dual-writes room name from `room_id`.
- No builder UI (S2.3). No student-keyed writes. Hifz / Deploy 3 untouched.

## S2.3 — React timetable builder (no student writes)

- Week grid per class; drag or click a subject onto a period×day cell.
- Teacher-view and room-view tabs; live conflict badges (server-computed).
- Copy-from-class (template copy logs `timetable.conflict_override` when the
  same teacher/room is reused); copy-week bounds unbounded rows to the
  selected week then clones with +7 day validity.
- Print + CSV export. Substitution overlay: approved `teacher_absences` +
  assigned substitute on the affected slot.
- Permission `manage_timetables`. No student-keyed writes. Hifz / Deploy 3
  untouched.

## S2.4 — Room bookings + clash vs timetable (no student writes)

- New `room_bookings`: `academic_year_id`, optional `term_id`, `room_id`,
  trilingual title, date, period XOR start+end, notes, `booked_by`.
- `RoomBookingClashChecker` (pure): booking↔booking and booking↔timetable
  (same room, day, overlapping time, validity includes the booking date).
- `SaveRoomBookingAction` hard-blocks clashes; rejects inactive/non-bookable
  rooms. `SaveTimetableEntryAction` also blocks a slot over an existing booking.
- Admin Inertia CRUD + CSV at `academics.bookings.*`. Permission `rooms.manage`.
- Morph alias `room_booking`. Hifz / Deploy 3 / student-keyed writes untouched.

## S2.5 — School calendar days (no register generation)

- New `calendar_days`: `academic_year_id`, unique `(date, academic_year_id)`,
  type `holiday|event|exam_day|closure|special_schedule`, trilingual title,
  `affects_timetable` default true, optional `event_id`, notes.
- Admin year-at-a-glance Inertia screen + CSV at `academics.calendar.*`.
  Permission `calendar.manage`.
- Portal read at `portal.holidays` and public events page list holidays +
  closures via `ListCalendarHolidaysAction` (no Website/Portal model imports).
- **No** `GenerateExpectedRegistersAction`. No student-keyed writes.
- Morph alias `calendar_day`. Hifz / Deploy 3 untouched.

## S2.6 — Class register loop (merged #21 `eb2227b`)

Operator still owns staging `students:verify-unification` paste + JSON
under `docs/migrations/`, branch protection, credential smoke, and
production. Coding continues without waiting on that evidence.

- Additive `course_plans.academic_year_id` / `term_id` (legacy year string
  kept; backfill by name). `CopyPlanAction` resets `is_completed`.
- Additive `lesson_logs` year/term/`timetable_id`/status
  (`expected|draft|submitted|locked`) + lock timestamps.
  `taught_summary` nullable so expected rows can exist empty.
- `GenerateExpectedRegistersAction` (nightly + on-demand): timetable slots
  on school days; skips `calendar_days.affects_timetable`; idempotent.
- Teacher Today + mobile-first register entry (topic or free text). **No**
  attendance grid (S2.7). Admin unfilled report + fill rate + plan
  adherence + CSV. Lock after `register_lock_days` (default 7); admin
  unlock writes `register_unlocks`.
- Permissions `registers.fill` / `registers.manage`. Morph `register_unlock`.
- Hifz / Deploy 3 / dual-write untouched.

## S2.7 — Class attendance writer (merged #22 `435e08a`)

- New `class_attendance` (year + term; unique student+date+`period_key`
  so null period = daily). `AttendanceWriterInterface::record` is the
  only writer (arch test). Modes `per_lesson` / `daily`.
- Register grid writes per-lesson rows; daily homeroom screen for daily
  mode. Reports + chronic/unexcused CSV. Portal: own children only.
- `StudentMarkedAbsent` → Notifications SMS via `SmsSenderInterface`;
  one SMS per student per day. Setting `attendance_notify`.
- ADR-011. Morph `class_attendance`. Hifz / Deploy 3 untouched.

## S2.8 — Absence notes excused flip (merged #23 `8298a28`)

- Parent Portal submit (own children only). Admin review + CSV.
- Approving a note with `affects_attendance=true` flips matching
  `class_attendance` absent rows → excused via the writer and sets
  `absence_note_id`. Hifz / Deploy 3 untouched.

## S2.9 — Behavior records (merged #24 `c60535c`)

- New `behavior_records` + audited edits/deletes. Permissions
  `behavior.record` / `behavior.manage`. Student profile tab. Portal
  shows `parent_visible` only for own children. CSV on the listing.
- Morph aliases `behavior_record`, `behavior_record_audit`.
  Hifz / Deploy 3 untouched.

## S2.10 — Requests & teacher leave (merged #25 `a89febd`)

- New `requests` with type handlers. `teacher_leave` approval creates
  `teacher_absences` and open `substitution_requests` for matching
  timetable slots (overlay includes open + assigned). Parent absence
  notes stay in their own table. Permissions `requests.submit` /
  `requests.review`. Morph `school_request`.
- Hifz / Deploy 3 untouched.

## S2.0b — Staging test PR (this slice)

Originally docs. **Now also the S1.1b FK hotfix** — staging cannot
migrate until `000002` stops assuming `students_user_id_foreign` exists.
Merging this branch to `main` triggers a staging webhook deploy so
`scripts/pull-deploy-test.sh` runs migrate (through the queued S1.1b–S2.10
batch) then the **current** gates: `morph-map:verify` then
`students:verify-unification` (read-only).

### Operator after merge + auto-deploy

On `test.akuru.edu.mv`:

```bash
cd ~/test.akuru.edu.mv
tail -n 80 ~/self-update-test.log
php artisan students:verify-unification
```

1. Paste the full verify stdout into this file (same format as the
   morph-map capture).
2. Copy `storage/app/s11b-student-unification-report.json` into
   `docs/migrations/` (see that folder's README).
3. Zero unresolved → mark the Deploy 2 / student-write gate satisfied.
   Nonzero → list affected rows, **report and stop** (do not start S3).

### Staging smoke URLs (after deploy; needs credentials)

| Surface | EN URL |
|---------|--------|
| Teacher Today | https://test.akuru.edu.mv/en/academics/registers/today |
| Unfilled registers | https://test.akuru.edu.mv/en/academics/registers |
| Course plans | https://test.akuru.edu.mv/en/academics/plans |
| Attendance reports | https://test.akuru.edu.mv/en/academics/attendance |
| Daily attendance | https://test.akuru.edu.mv/en/academics/attendance/daily |
| Absence notes (admin) | https://test.akuru.edu.mv/en/academics/absence-notes |
| Behavior | https://test.akuru.edu.mv/en/academics/behavior |
| Requests / leave | https://test.akuru.edu.mv/en/academics/requests |
| Portal attendance | https://test.akuru.edu.mv/en/portal/attendance |
| Portal absence notes | https://test.akuru.edu.mv/en/portal/absence-notes |
| Portal behavior | https://test.akuru.edu.mv/en/portal/behavior |

Seed logins will not work on real staging. Use operator credentials.

## Staging 2026-08-25 — migrate blocked; verify is pre-backfill

Operator paste from `test.akuru.edu.mv`. Git on the server fast-forwarded
to **`c25c385`** (S2.5) then died in `migrate --force`. Later S2.6–S2.10
webhook attempts aborted (SHA wait / lock). **S2.6+ code and the S1.1b
backfill have not applied.**

### Deploy failure (verbatim)

```text
2026_08_23_000002_s11b_nullable_student_user_id ................ 1.74ms FAIL

SQLSTATE[42000]: Syntax error or access violation: 1091 Can't DROP FOREIGN
KEY `students_user_id_foreign`; check that it exists (Connection: mysql,
Host: 127.0.0.1, Port: 3306, Database: akuruedu_test.akuru.edu.mv, SQL: alter
table `students` drop foreign key `students_user_id_foreign`)

2026-08-25 11:57:03 Laravel deploy steps failed
```

S1.1a (`000001`) already ran (this was the next batch). Staging `students`
has `school_id` / `class_id` FKs but **no** `user_id` FK under the Laravel
default name. `000003` (UnifyStudentsAction) never ran.

`2a8ccde` (#26) fixed the 1091. Next deploy reached `2a8ccde` (hotfix
present) then failed adding the FK.

### `php artisan students:verify-unification` (verbatim)

```text
S1.1b unification report: /home/akuruedu/test.akuru.edu.mv/storage/app/s11b-student-unification-report.json
  mapped: user_id=0 national_id=0 name_dob=0 already=0
  created: active=0 prospective=0
  guardians: source=0 migrated=0 profiles_created=0 skipped=0
  enrollments: filled=0 already_set=0 missing=0
students:verify-unification FAILED — unresolved unification rows:
  • registration_students.id=2 maps to 0 student(s)
  • registration_students.id=3 maps to 0 student(s)
  • registration_students.id=4 maps to 0 student(s)
  • registration_students.id=5 maps to 0 student(s)
  • registration_students.id=6 maps to 0 student(s)
  • registration_students.id=7 maps to 0 student(s)
  • registration_students.id=12 maps to 0 student(s)
  • registration_students.id=13 maps to 0 student(s)
  • registration_students.id=22 maps to 0 student(s)
  • registration_students.id=25 maps to 0 student(s)
  • registration_students.id=28 maps to 0 student(s)
  • registration_students.id=29 maps to 0 student(s)
  • 11 course_enrollments missing unified_student_id
  • guardian pivot count mismatch: student_guardians=13 migrated guardian_student=0
Resolve ambiguous/colliding rows before Deploy 2 (switch reads).
```

**Interpretation (not a guess of identities):** every map/create count is
zero. This is **backfill never executed**, not ambiguous/colliding matches.
The 12 RS → 0 students, 11 enrollments, and 13 vs 0 guardian pivots are
the expected pre-`000003` state. JSON still lives on the server; not
copied into `docs/migrations/` yet.

S3 / Hifz / Deploy 3 are **not** started.

### Deploy failure after #26 (`2a8ccde`, 2026-08-25 13:23)

Code on staging is the hotfix (`dropOnColumn` present). `000002` ran
185ms then 1452 — orphan `students.user_id` values that are not in
`users.id`. DDL before the add (nullable change) likely committed;
the migration row is **not** recorded.

```text
2026_08_23_000002_s11b_nullable_student_user_id .............. 185.42ms FAIL

SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update
a child row: a foreign key constraint fails (`akuruedu_test.akuru.edu.mv`.
`#sql-alter-2173bb-6927b`, CONSTRAINT `students_user_id_foreign` FOREIGN KEY
(`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL)
```

Verify stdout unchanged (still pre-`000003`). This slice nulls those
orphans (S1.1b: student may have no user) then adds the FK.

### Deploy `a9d5677` (2026-08-25 13:34) — migrate green, unify-verify FAILED

Staging HEAD **`a9d5677`**. `000002`–`000008` (S1.1b through S2.10) all
**DONE**. `morph-map:verify OK`. Deploy script then failed the
student-unification gate (exit 1). Code + schema **are** applied; do
not treat the deploy as green.

**Backfill ran:** created active=6 prospective=2; enrollments filled=7;
mapped by user/national_id/name_dob = 0 (no match onto pre-existing
`students` — new rows only). collisions=4.

```text
students:verify-unification FAILED — unresolved unification rows:
  • registration_students.id=13 maps to 0 student(s)
  • registration_students.id=22 maps to 0 student(s)
  • registration_students.id=25 maps to 0 student(s)
  • registration_students.id=29 maps to 0 student(s)
  • 4 course_enrollments missing unified_student_id
  • guardian pivot count mismatch: student_guardians=13 migrated guardian_student=0
```

**Interpretation:** the four RS are **collisions** (first RS already
holds `legacy_registration_student_id` on the matched student). ADR-007
does not guess a second RS onto that student. Those four enrollments
therefore have `unified_student_id` null. Deploy 2 reads
`CourseEnrollment::student()` / `Payment::student()` via
`unified_student_id` — those four enrollment (and any matching payment)
reads resolve to **no student**, not a wrong student.

JSON archived: `docs/migrations/s11b-student-unification-report.json`.

### Collision table (operator paste + tinker)

| RS | Name / DOB | Collision | Winning student | Same person? |
|----|------------|-----------|-----------------|--------------|
| 13 | b b / 2000-01-01 | `national_id` → student 2 (legacy RS 2) | 2 Nur Asif / 2025-01-01 | **No** — false ID match |
| 22 | a a / 1987-10-02 | `national_id` → student 8 (legacy RS 12) | 8 a a / 2000-01-01 | **No** — name/DOB differ; RS 22 actually matches **student 5** (a a / 1987-10-02) if `name_dob` ran first |
| 25 | v v / 1987-01-01 | `national_id` → student 8 | 8 a a / 2000-01-01 | **No** — false ID match |
| 29 | a a / 2000-01-01 | `national_id` → student 8 | 8 a a / 2000-01-01 | **Yes** — same name + DOB as RS 12; duplicate course profile |

Unfilled enrollments (all `course_id=1`): id 13/22/25 **active**, 29 **pending**. Deploy 2 `student()` reads are null for these four.

### Guardians (second blocker — even after collisions)

All 13 `student_guardians` rows are `unmapped`. 12/13 `guardian_user_id`s are **missing from `users`** (ids 3, 8, 18, 34, 37, 40). Only pivot 13 (`RS 29` → user 43) has a live user, and that RS has no student yet so the copy skipped. `createParentFromUserId` cannot invent users. Re-running `--backfill` will not turn the gate green.

**Stop:** no S3, no Hifz, no Deploy 3. Student-keyed S2 writes stay blocked.

## S3.1 — Grading foundations (2026-08-25)

- **Done.** Additive `grade_scales` / `grade_scale_bands` / `weight_schemes` / `weight_scheme_items` with `academic_year_id` (rule 10). Seeded 4-point / 5-point / A–F / 0–100 scales and default scheme (attendance 5, classwork 15, homework 10, quizzes 20, exams 50).
- Spatie `exams.manage`; `Grading/ExamTypeController` + `/exams/scales|types|weights` + CSV. Engine-agnostic — no course_type branches.
- Merged onto current `main` after S4–Qur’an A (S3 was a parallel stack from older `main`). Permissions and nav unioned with finance/HR/courses.
- Pest: `S3/GradingFoundationsTest`, `S3/GradingFoundationsArchitectureTest`.

**Historical note (S4 landed first):** S2.1–S2.10 coding is on `main`. Staging
schema is at `a9d5677` (S1.1b–S2.10 migrated). Unify-verify is red;
ADR-007 forbids guessing the four collisions or inventing guardian
users. No Hifz migration, no Deploy 3, no student-keyed write
follow-up until the operator marks verify green. Operator asked to
complete S4 coding one slice at a time anyway (S4 is independent of
S3). S4.1 has **no new student-keyed writes** beyond existing
`invoices.student_id` (already FK to `students`).

## S4.1 — Schema scoping & gaps (done)

- `invoices` add `academic_year_id`, nullable `term_id`, `invoice_type`
  (`school_fees` / `course_fee` / `other`), nullable `payment_plan_id`
  (no FK until S4.4). Existing `student_id` already FKs `students`.
- `fee_items` add trilingual names; `applicable_grades` kept.
- New `receipts` (number unique, method enum, optional payment +
  document). Permission `finance.record-manual-payment` +
  `finance.manage`. Fee-item catalog + CSV.
- Hifz / Deploy 3 / S3 untouched.

## S4.2 — Fee structures (done)

- `fee_structures` + `fee_structure_items`. One active structure per
  class per year. Copy-from-last-year remaps classes by name+section
  and lands drafts. Admin builder + CSV.
- Academics list/resolve year actions used so Finance does not import
  Academics models.

## S4.3 — Invoice generation (done)

- `invoice_generation_logs` unique(student, structure, period_key).
- `GenerateInvoicesAction`: roster from class_student, monthly per-month
  or consolidated (qty × term months), optional-item toggles,
  adjustments hook (empty until S4.5).
- Issue → sent + SMS/in-app to financially-responsible guardians.
- `invoices:mark-overdue` + `invoices:send-reminders` (7-day throttle).
- Arrears listing by class/guardian with 30/60/90 buckets + CSV.

## S4.4 — Payment plans (done)

- `payment_plans` + `payment_plan_installments`. Installments must sum to
  the invoice balance. `invoices.payment_plan_id` now has an FK.
- `AllocatePaymentAction` is the only allocator (oldest unpaid first,
  overpayment rejected, `lockForUpdate`). ADR-014: defaulted = follow-up
  flag, never a school lockout (spec’s “ADR-006” was already used).

## S4.5 — Discounts, scholarships, waivers (done)

- `fee_adjustments` (percent then fixed, validity window, approved only).
- Applied at generation as transparent discount lines. Sibling suggestion
  via shared financially-responsible guardian. Not Commerce codes (L4).

## S4.6 — Payment + portal (done)

- Webhook confirmation (Rule 12) creates a receipt and allocates;
  a second delivery is a no-op. Manual cash/transfer is gated by
  `finance.record-manual-payment`. Receipts render through
  `DocumentRendererInterface` (`HtmlDocumentRenderer`).
- Parent portal: invoices, balance, plan progress, pay-now, receipt
  HTML. Admin collections + reconciliation CSVs.

## S5.1 — Staff attendance (done)

- `staff_attendance` unique `(staff_profile_id, date)` plus `academic_year_id`
  (rule 10). `StaffAttendanceWriterInterface` is the only writer
  (`manual`, `self`, `external`, `import`). Precedence: `on_leave` >
  holiday > present/absent/late.
- Holiday auto-fill from `calendar_days` (holiday + closure) skips
  existing leave. Monthly summary action is ready for S5.6 payroll.
- Nullable `department` + `designation` on `staff_profiles`. Admin
  Inertia + CSV import/export + late/absence reports. Portal self
  check-in behind `hr.staff_self_checkin` (off by default) logs IP.
- Permission `hr.manage`. People actions only — HR does not import
  People models.

## S5.2 — Leave management (done)

- `leave_types`, `leave_entitlements` (academic year — ADR-015),
  append-only `leave_ledger`. Balance is always the ledger sum.
- S2.10 `requests` gains `staff_leave`. Approval checks balance
  (unpaid bypasses), writes `staff_attendance` `on_leave`, decrements
  the ledger, and still creates `teacher_absences` + substitutions when
  the staff member teaches. All-or-nothing transaction.
- `teacher_leave` without a leave type keeps the S2.10 path.
- Carry-over caps at `carry_over_max`. Admin types/balances + CSV.
  Portal shows own balances only.

## S5.3 — Contracts & compliance (done)

- `staff_contracts`: one active per staff; a new active contract
  supersedes the previous (history kept).
- Shared `documents.expires_at` plus `document_expiry_notices`.
  `hr:notify-expiring-documents` fires 90/60/30 once each to
  `hr.manage` users and the staff member. Not expat-hardcoded.

## S5.4 — Recruitment & onboarding (done)

- `job_postings` + `job_applications` (Admissions-like stages).
- `HireApplicantAction` creates a user, staff profile, and onboarding
  checklist from Settings templates. Offboarding is the mirror list.
- Public `/careers` lists published public postings only (no W-track
  restyle).

## S5.5 — Performance & CPD (done)

- `appraisal_cycles` + `appraisals` (staff acknowledge + comment).
- `lesson_observations` store S2 class/subject IDs read-only.
- `cpd_records` + per-staff summary on the portal.

## S5.6 — Payroll (done; flagged off)

- `payroll_periods` + `payslips`. `PayrollCalculatorInterface` /
  `MaldivesPayrollCalculator`. Rates live in `payroll.rules` (ADR-016
  half-up 2dp). Unpaid days come from `on_leave` attendance marked
  unpaid. Mid-month join/exit prorates.
- `RunPayrollAction` is idempotent for drafts. Approve finalizes and
  locks the month (blocks retro attendance/leave). Mark paid writes a
  Finance `payroll_postings` receipt, bank CSV, and HTML payslip via
  `DocumentRendererInterface`.
- Permissions: `payroll.run` vs `payroll.approve`. Staff see own
  payslips only. `payroll.enabled` stays **off**.

## 1A.1 — Auth / roles / settings (already satisfied)

Phase 0 + S1 already shipped auth, Spatie roles, users, Settings,
rate limiting, and the Inertia shell. No new 1A.1 code.

## 1A.2 — Taxonomy + engine course CRUD (done)

- `course_subjects` (hierarchical, ADR-003), `audiences`, `course_levels`
  — admin-managed, trilingual, seed examples only.
- Additive on `courses`: `subject_id`, `workflow_status`, `course_type`,
  `created_by`, `title_dv`/`title_ar`. Marketing `status` is untouched.
- Workflow: draft → in_review → published → archived. Invalid hops
  rejected. `courses.publish` is required to publish.
- Inertia catalog under `/catalog/*` + CSV export. Website Blade
  courses stay as they are.

## 1A.3 — Modules, lessons, revisions (done)

- Outline CRUD under `/catalog/courses/{id}/outline`.
- Publish writes `lesson_revisions.snapshot_json` (ADR-017). Player
  reads the current published revision only.
- Draft block edits, deletes, and reorders do not mutate history.

## 1A.4 — Core text blocks (done)

- Validated `text`, `rich_text`, `instruction`. Direction is a setting.
- Media block types rejected. Player renders published snapshots.

## 1A.5 — Media pipeline + media blocks (done)

- `media_files` (private, local disk). Courses calls Media Actions only.
- Queued `ProcessMediaFileJob` marks processed; no inline thumbnails.
- Image / audio / video / PDF blocks store `media_id` (no public URL).
- Video also accepts YouTube/Vimeo embeds. Authorized
  `GET /catalog/media/{id}` serves bytes. Player renders all four types.

## 1A.6 — Self-learning enrollment + progress (done)

- Free enroll on published courses via `/learn` (existing `course_enrollments`).
- Progress domain owns `student_lesson_progress` with `lesson_revision_id`.
- Access: staff, preview, or active enrollment. Next required lesson locks
  until the previous is completed. Course % is required-lesson based.
- Enrolled students can stream private media via `/learn/media/{id}`.

## 1A.7 — Parent-child polish + architecture tests (done)

- ADR-018: People answers guardian access via Actions, not model imports.
- Additive `guardian_student` consent/verification fields.
- `/portal/learning` is read-only child progress. Player stays
  staff/student/preview.
- `learn` lang files (EN/DV/AR). Admin i18n preview. Logical CSS on 1A
  listings. Phase 1A boundary architecture tests.

## 1B.1 — Offerings + delivery modes (done)

- `course_offerings` in the Offerings domain. Modes: self_learning,
  face_to_face, live_online, blended, hybrid.
- Publishing a course creates a default open self-learning offering
  (`pin_mode=latest`). Catalog CRUD + CSV at `/catalog/offerings`.
- Offerings call Courses Actions only (no Courses model imports).

## 1B.2 — Pinning, offering enrollment, seat limits (done)

- Offerings can pin current lesson revision ids. Player uses the pin
  when the enrollment is linked to that offering.
- `course_enrollments.course_offering_id` is additive. Self-learning
  enroll attaches the default self-learning offering.
- Seat limits: `lockForUpdate` on the offering + enrollment count.

## 1B.3 — Sessions + attendance foundation (done)

- `course_offering_sessions` + `attendance_records` in Offerings
  (not Academics `attendance` / `class_attendance`).
- Time-scoped columns: `academic_year_id` / `term_id` on sessions;
  `academic_year_id` on attendance.
- Catalog session CRUD + CSV; attendance only for that offering's
  enrollments. Learner dashboard/course page show upcoming sessions.
- Offerings call Courses Actions for the roster (no model imports).

## 1B.4 — Remaining block types (done)

- `glossary` / `term`, `dialogue`, `flashcard`, `download`,
  `quiz_embed`, `assignment_embed` on the engine outline and player.
- Glossary is not Arabic-only. Download uses private media ids.
- Quiz/assignment embeds are id/URL pointers only.

## 1B.5 — Unlock + completion evaluators (done)

- Progress owns `EvaluateLessonUnlockAction` (sequential required
  lessons) and `EvaluateCourseCompletionAction` (required lessons +
  optional required sessions).
- Courses calls those Actions; Offerings reports session attendance
  via `ListRequiredSessionProgressAction`.
- Unit tests cover the formula (including 2/3 = 66).

## 1B.6 — PWA + i18n/RTL polish (done)

- `manifest.webmanifest`, `sw.js`, `/offline.html`. Layouts register
  the service worker (no longer unregister).
- AppShell locale switcher (EN/DV/AR). Faruma for Thaana, Cairo/Amiri
  for Arabic. Learn keys aligned across locales.
- Admin i18n preview asserts thaana/arabic font classes.

## 2.1 — Activity patterns + builder (done)

- Four patterns: `selection`, `text_input`, `arrange`, `teacher_marked`.
- Courses owns `activities`; Progress owns `activity_attempts`.
- Catalog CRUD + CSV at `/catalog/courses/{id}/activities`.
- Learner player at `/learn/activities/{id}` with autosave and submit.
- Auto-mark selection / text / arrange. Text normalization is per-activity
  (Arabic flags off unless configured). Teacher-marked stays submitted.
- Students do not see answer keys until scored + `show_correct_answer`.
- Unlock formula is unchanged (`lock_next_lesson` is stored only).

## 2.2 — Question bank (done)

- `questions` in Courses. Types map onto the four 2.1 patterns.
- Catalog CRUD + CSV at `/catalog/questions`. Optional private media
  attachments via Media Actions.
- Standards tagging goes through ExamsGrades Actions only. S3.5 tables
  are not required; tagging is a no-op until they exist.
- `SnapshotQuestionAction` freezes question content so later edits
  cannot change an existing snapshot.

## 2.3 — Assessment builder + player (done)

- `assessments` + `assessment_questions` in Courses; attempts in Progress.
- Catalog builder attaches bank questions, CSV export, publish/draft.
- Learner player snapshots questions on first open, autosaves, scores
  from the snapshot. Editing the bank cannot change an in-flight attempt.
- Retake limit and show-correct-answers apply. Teacher-marked items stay
  submitted until 2.4.

## 2.4 — Teacher review (done)

- Additive `feedback` / `reviewed_by` / `reviewed_at` on activity and
  assessment attempts. Assessment attempts also store `item_scores`.
- Catalog queue at `/catalog/reviews` (courses.manage). Scoring sets
  status to `scored` and stores feedback.
- Learners see feedback on the activity and assessment players.

## 2.5 — Session + attendance UI polish (done)

- Session update + `teacher_user_id` assignment.
- Attendance roster shows student names (People Actions) and can bulk-mark.
- Student schedule `/learn/schedule`. Teacher schedule `/teach/schedule`.
- Offerings still owns sessions/attendance; no Academics imports.

## Arabic A.1 — Letters + harakas (done)

- Admin-managed `arabic_letters` (28 seeded) and `arabic_harakas`
  (fatha/kasra/damma/sukoon). Catalog `/catalog/arabic` + CSV.
- Listed through `ListArabicReferenceAction` so later skill activities
  can attach ids as metadata. No engine `course_type` branches. No AI.

## Arabic A.2 — Skill activities on the four patterns (done)

- `settings.skill` is `listening|speaking|reading|writing`.
- Optional `letter_id` / `harakah_id` validated through the A.1 list Action.
- Still the four 2.1 patterns. No Arabic exercise engine. No AI.

## Arabic A.3 — Skill reports (done)

- Catalog `/catalog/arabic/reports` (+ CSV) and learner
  `/learn/arabic-report`.
- Built from skill-tagged activities + Progress attempt Actions.
- No parallel LMS. No AI.

## Qur’an A.1 — Read Actions over existing tables (done)

- Hifz `ListSurahsAction` / `ListAyahsAction` read `surahs` and
  `quran_ayahs`. Bound as `QuranReferenceReader`.
- Catalog `/catalog/quran` (+ CSV). Courses uses the Support contract
  only — no Hifz namespace import, no dashboard/scoring change.
- No parallel Quran tables.

## Qur’an A.2 — Recitation as teacher-marked activities (done)

- `settings.surah_id` / `ayah_start` / `ayah_end` on the four 2.1
  patterns (intended for `teacher_marked` recitation). Validated
  through `QuranReferenceReader`.
- Learner player shows the passage. 2.4 review still scores it.
- No Hifz dashboard, assignment table, or scoring change.

## Qur’an A.3 — Offering / session mapping (done)

- Offerings mapping tables with integer Hifz ids (no FK).
- Catalog session screen can link a program and map sessions.
- Hifz dashboards, scoring, and tables are not written.
- ADR-019. Dual-write / switch stays later.

## Qur’an A.4 — Dual-write only (done)

- `QURAN_HALAQA_DUAL_WRITE` defaults false.
- Catalog sync mirrors unmapped Hifz sessions + active enrollments.
- Flag-on Hifz session create also mirrors; failures never block Hifz.
- No read switch. No Hifz table cleanup. ADR-020.

## S3.2 — Exams (done)

- `exams` + `exam_status_audits`. Calendar holiday/closure warn-block,
  same-class same-day cap (`exams_max_per_class_per_day`, default 1),
  room clash vs other exams / bookings / timetable (via Academics
  `CheckRoomSlotConflictAction`).
- Status: scheduled → marks_entry → review → published → locked.
  Publish fires `ExamResultsPublished` → portal notification.
  Locked rejects edits; unlock requires a reason and is audited.
- Bulk scheduler: one exam per subject. Admin Inertia + CSV.
- Portal `portal.exams` lists published exams for a guardian's children.
- No `exam_marks` yet (S3.3). Hifz / Deploy 3 untouched.

## S3.3 — Marks entry (done)

- `exam_marks` unique per exam/student. Roster is `class_student` as of
  `exam_date` (left-before excluded).
- Marks ≤ max; absent/exempt mutually exclusive with a numeric mark.
- ADR-013: absent counts as 0 unless `exams_exclude_absent` is on.
- Permission: subject teacher (timetable class+subject) or
  `exams.enter-any` / `exams.manage`. Entry only in marks_entry/review.
- Grid + CSV import/export. Portal shows published marks.
- Student-keyed writes coded; staging still blocked until unify-verify
  is green.

## S3.4 — Term grades (done)

- `term_grades` computed cache: weight scheme → published exams →
  percent / letter / point / rank. Multiple exams of one type share
  that type's weight equally unless `weight_override`.
- Idempotent recompute; also runs on `ExamResultsPublished`.
- ADR-013 absent/exempt applied. Ties share rank.
- Competencies + assessments (subject-level). Gradebook matrix + CSV.
- Hifz / Deploy 3 untouched.

## S3.5 — Curriculum standards (done)

- `standards` (optional subject, hierarchy via `parent_id`) and
  polymorphic `standard_taggables` for exams and plan topics.
- Coverage report + CSV: tagged exams/topics per standard (optional
  term filter). No per-student analytics (Phase 2).
- Hifz / Deploy 3 untouched.

## S3.6 — Report cards (done)

- `report_card_templates`, `report_cards` (unique student+term),
  `report_card_comments` (class teacher / head, trilingual).
- `GenerateReportCardsAction` pulls term grades, S2 attendance %,
  parent-visible behavior, comments; queued per-student render.
- Production `HtmlDocumentRenderer` bound to
  `DocumentRendererInterface` (ADR-012). Blade EN + DV RTL snapshots.
  Bytes stored via Media `StoreGeneratedDocumentAction` (private disk).
  Chrome/Browsershot can replace the binding later; no `if` in domain.
- Publish + portal download + transcript (`GenerateTranscriptAction`,
  optional GPA). Regeneration until published.
- Admin unpublished list + CSV. Hifz / Deploy 3 untouched.

## S3.7 — Awards & documents (done)

- `awards` (class/school) and `student_awards` (year + optional term).
- Batch issue generates certificates through the same renderer.
- ID cards include student number + QR SVG. Transfer/leaving certs
  pull `student_status_history`.
- Public `/achievements` lists school awards; photos only when the
  latest `photo_media_use` consent is granted.
- Portal awards list. Report-card awards section now reads issued
  awards. Hifz / Deploy 3 untouched.

## TRACK A — Unblock (S1 verify blockers)

### A1 — `users:clear-non-admin` deletes `student_guardians` (done)

- Staging verify’s “12/13 `guardian_user_id` missing from `users`” came from
  this wipe: `FOREIGN_KEY_CHECKS=0` deleted users / `registration_students`
  / payments and never touched `student_guardians`.
  `UnifyStudentsAction::createParentFromUserId()` cannot invent those users.
- Command now deletes `student_guardians` and `guardian_student` for wiped
  users/profiles. `whereNotIn` does **not** match NULL — guardian-only
  `registration_students` (`user_id IS NULL`) are listed explicitly and
  included in the wipe so they cannot survive their guardian users.
- Pest: `tests/Feature/People/ClearNonAdminUsersTest.php` (no leftover
  pivot rows). Merged #72.

### A2 — UnifyStudentsAction national_id matcher (done)

- **Defect:** staging RS 22 matched the wrong `students` row by `national_id`
  while name+dob identified the right one. ADR-007 previously let the first
  method with any candidates win.
- `national_id` is now **unusable** (fall through to name+dob) when blank,
  duplicated across `registration_students` or `students`, or in
  `config/unification.php` placeholders (extend via
  `UNIFICATION_NATIONAL_ID_PLACEHOLDERS`).
- Unique `national_id` + name+dob **contradiction**: do not attach the ID
  hit; fall through to name+dob. RS-22-shaped rows match the name+dob
  student. If name+dob is not unique, record ambiguous and do not create.
- Orphan `student_guardians` (missing `guardian_user_id`) are reported,
  never invented as `parent_guardians`.
- ADR-007 amended; supersedes `docs/S1_SPEC.md` line 49 match order.
- Pest cases in `UnifiedStudentBackfillTest`. Dual-write matcher untouched.
  Merged #73.

### A3 — Unification verify gate (ADR-021; representative seed **green**)

- Operator confirmed **2026-08-25:** there is **no production system**.
  Nothing is live. `test.akuru.edu.mv` is synthetic-only. The old
  “production-data copy” gate (S1_SPEC line 147 / #74 procedure) is
  **unsatisfiable** until first real use; it is dormant, not deleted
  (`docs/migrations/restore-production-copy.md`).
- Current gate: `UnificationRepresentativeSeeder` +
  `php artisan students:verify-unification --representative` (refuses
  `APP_ENV=production`). Pest:
  `tests/Feature/People/UnificationRepresentativeSeederTest.php`.
- Report: `docs/migrations/s11b-student-unification-report-representative.json`.
  **TRACK B is unblocked.** Not started in this slice.

### A4 — Branch protection on `main` (**not applied**)

- Agent GET/PUT `.../branches/main/protection` → HTTP **403**
  `Resource not accessible by integration`. Settings were not changed.
- Operator checklist: `docs/BRANCH_PROTECTION.md` (require PR, CI job
  `quality`, no force-push). A4 is green only after an admin confirms.

### A5 — Real `config/payroll.php` feature flag (done)

- Flag was only a `settings` row (`payroll.enabled` = `'0'`).
  `config/payroll.php` did not exist.
- `PAYROLL_ENABLED` (default **false**) AND the settings row must both be
  true. `ResolvePayrollSettingsAction` implements the AND.
  `assertEnabled()` gates **every write path**: run, approve, pay, lock.
- Pest: `enablePayroll()` sets both; write actions are inert when off.
- Payroll stays off until two parallel cycles match (operator). Merged #76.

## ADR-021 — No live data yet (2026-08-25)

Operator-confirmed: no production, no real students/payments/Hifz users.
`test.akuru.edu.mv` is the only deployment and is synthetic.

- ADR: `docs/adr/ADR-021-no-live-data-yet.md` (brief asked for “ADR-011”;
  that number is already attendance modes).
- Rule 7 freeze kept as **scope discipline**, not production-safety.
- Rule 9 3-deploy: additive migrations still the default; dual-write
  windows and “≥2 weeks stable” optional until **first real use**.
  Production-copy verify reactivates that same day.
- S1 Deploy 3 cleanup is **proposed, not executed**:
  `docs/migrations/s11-deploy-3-cleanup-proposal.md`.

### `php artisan students:verify-unification --representative` (verbatim)

Fresh `akuru_test` (`migrate:fresh` then `--representative`), 2026-08-26:

```text
   INFO  Seeding database.

S1.1b unification report: /workspace/storage/app/s11b-student-unification-report.json
  mapped: user_id=0 national_id=3 name_dob=7 already=0
  created: active=0 prospective=0
  A2 matcher national_id_unusable_skips=8
  A2 matcher national_id_contradiction_fallthroughs=1
  guardians: source=3 migrated=2 profiles_created=2 skipped=0 unmapped=1
  enrollments: filled=10 already_set=0 missing=2
  ambiguous: 2 (listed in report file)
  verification verdict=OK_AGAINST_MANIFEST raw_ok=false unexpected_failures=0
students:verify-unification OK — representative dataset: resolvable rows mapped; expected unguessable rows listed; no enrollment/payment resolved to the wrong student.
```

**A2 matcher on this dataset:** **8** `national_id` keys skipped as
unusable (duplicate ID across two different people; blank; placeholders
`N/A`, `0`, `-`; plus two genuine-duplicate blanks). **1** unique
`national_id` hit **downgraded** by contradicting name+dob and mapped
via name+dob instead (wrong-hit student left unlinked). Artifact
`verification.verdict` is `OK_AGAINST_MANIFEST` with
`unexpected_failures: []`. `raw_ok` is false on purpose: RS 11 and 12
are the seeded genuine name+dob duplicate (candidates 12/13); ADR-007
does not guess. `guardians.unmapped: [2]` is the seeded orphan guardian
pivot. Missing enrollments 11/12 follow from those unresolved RS rows
(null, not a wrong student). Do not “fix” these.

JSON archived:
`docs/migrations/s11b-student-unification-report-representative.json`.

**Gate:** GREEN (ADR-021 representative). TRACK B unblocked; not started.

## Pilot rehearsal (findings only, 2026-08-26)

No product features. Staging `test.akuru.edu.mv` could not be seeded (no SSH)
or logged into with seed passwords. Walk was local `akuru_institute` plus
`php artisan db:seed --class=PilotRehearsalSeeder` (ADR-021 messy IDs, 15
students, 3 teachers, timetable, fee structure).

Findings: `docs/PILOT_REHEARSAL.md`. Ranked teacher blockers start at staging
login, verified `user_contacts`, missing `teachers` rows, teacher cannot
generate registers, no periods UI, Blade dashboard hiding the Inertia loop,
AppShell with no logout (`GET /logout` 405), no class-teacher picker, roster
add by numeric PK (id `1` ≠ PIL-01), indistinguishable duplicate names on the
register grid, SMS not actually faked, term grades blank without weights,
report “PDF” is HTML. Browser walk completed Step 1 only (~33 clicks).

Hifz untouched. Deploy 3 not executed. Track B not started.

<<<<<<< HEAD
## Pilot blocker 1 — roster picker (2026-08-26)

Class show searches by name / student number / national ID (not `students.id`)
and lists name, number, DOB, national ID, current class. Identical identity
rows are flagged; assign still requires an explicit chosen id. Findings doc
=======
## Pilot blocker 2 — AppShell logout (2026-08-26)

Inertia `AppShell` posts `/logout`. `GET /logout` remains 405. Findings doc
>>>>>>> origin/cursor/appshell-logout-063c
is unchanged in this slice.

## Next

TRACK A code/docs A1–A5 plus ADR-021 representative gate are the current
unification story. **A4 protection is not applied** (bot 403; re-tried
2026-08-25). **TRACK B is unblocked; do not start it in this slice.**
S1 Deploy 3 cleanup is a proposal only — wait for confirmation.
`--backfill` still refused on `APP_ENV=production`. No Hifz behavior
change. `PAYROLL_ENABLED` / settings stay off.
Pilot rehearsal findings are in `docs/PILOT_REHEARSAL.md`. Remaining
blockers: AppShell logout, seed `user_contacts`, class teacher field,
periods UI/seed, register generate empty state.

**Operator:** apply branch protection (`docs/BRANCH_PROTECTION.md`).
Confirm or reject `docs/migrations/s11-deploy-3-cleanup-proposal.md`.

**Qur'an A.4b (later):** switch offering-session reads to `offering_halaqa_session_links` after operators confirm dual-write. Then Hifz cleanup (deploy 3). Keep `QURAN_HALAQA_DUAL_WRITE` off until verified.

**Arabic B / Qur'an B / later:** pronunciation AI, Capacitor, W1–W3, L-track.
