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
| Manual post-deploy | `php artisan morph-map:verify` then `php artisan permission:cache-reset` (deploy-gate slice not on `main`; first gated deploy would still be ungated anyway) |

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

**Gate:** GREEN. Credential smoke (admin login + nav, portal, Hifz, enrollment OTP `7820288`/`7972434`, BML sandbox) is now unblocked and still **pending operator**.

**Follow-up (not this slice):** flip to `Relation::enforceMorphMap()` after
production verification. S1.1a ADR for guardian/enum becomes **ADR-006**.

## Next (Phase S1 — do not start until morph-map hotfix is on staging + credential smoke passes)

- Student unification and course engine per `docs/S1_SPEC.md`
- Per-domain `routes.php` split (early S1 infra)
- Shrink architecture baselines as domains decouple
