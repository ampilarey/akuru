# Status

## Phase 0 — Foundation (closure: code + CI on `main`; staging deploy **blocked**)

**Honest summary:** Domain moves, contracts, architecture baselines, and CI are done on `main` at **`e84c657`**. Staging deploy and full smoke on `test.akuru.edu.mv` are **not complete** — agent has no SSH to cPanel; operator must run server deploy (see `scripts/deploy-staging-phase0.sh`).

### Staging verification (2026-06-13)

| Item | Result |
|------|--------|
| Target URL | https://test.akuru.edu.mv |
| Expected commit | `e84c657` |
| Deployed commit on server | **Unknown** — not pulled by agent (no SSH) |
| `.env` / DB backup on server | **Not run** by agent |
| Server `git pull` + migrate + build | **Pending operator** |

**Pre-deploy browser smoke (current live site, commit unverified):**

| Area | Result |
|------|--------|
| `/up` health | 200 OK |
| Public EN/DV/AR | 200 OK |
| Courses, contact, gallery, news | 200 OK |
| `/inertia-test` | 200 — “Inertia + React works” |
| Login page | Loads |
| BML / enrollment / portal / admin / Hifz | **Not tested** (needs credentials + post-deploy commit) |

**Local automated (repo at `e84c657`, not server):**

| Check | Result |
|-------|--------|
| Pest | 75 passed, 1 todo |
| Pint | PASS (468 files) |
| PHPStan | ~410 errors (informational) |
| GitHub Actions `main` | Green — [run 27439531612](https://github.com/ampilarey/akuru/actions/runs/27439531612) |

**Verdict:** `PHASE 0 STAGING BLOCKED` — deploy `e84c657` on server, then re-run smoke + server-side tests.

### 0.1 Install & tooling
- Inertia + React, Pest, Larastan, ADR template, CI (`pint` + `pest` + architecture suite)
- **CI green on `main`:** [GitHub Actions run 27439531612](https://github.com/ampilarey/akuru/actions/runs/27439531612) (Pint + Pest 75 passed; PHPStan informational)

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
| Route-name snapshots green | **Done** (75 Pest tests, 1 arch todo) |
| `app/Models` / `app/Services` gone | **Done** (directories removed) |
| Pest + arch in CI | **Done** on `main` after closure push |
| Per-domain `routes.php` | **Deferred** → S1 |
| Manual smoke + production deploy | **Blocked** — staging pull pending (operator); production not started |
| PHPStan blocking CI | **No** (informational only) |

### Closure slice (no behavior change)
- CI: MySQL 8 + `APP_KEY` in `phpunit.xml`
- Removed 6 unreferenced Laravel UI auth stubs; **`VerifyEmailController` kept** (routed)
- Fixed duplicate `Controller` imports (`AdminUserController`, `InstructorController`)

## Next (Phase S1 — do not start until staging smoke passes)

- Student unification and course engine per `docs/S1_SPEC.md`
- Per-domain `routes.php` split (early S1 infra)
- Shrink architecture baselines as domains decouple
