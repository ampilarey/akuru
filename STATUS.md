# Status

## Phase 0 — Foundation (code complete; closure audit applied)

### 0.1 Install & tooling
- Inertia + React, Pest, Larastan, ADR template, CI (`pint` + `pest`)

### 0.2 Domain skeleton
- 20 domain providers + ADR-001 (single-institute, `school_id` retained)

### 0.3 Move map (namespace/route only)
All 13 checklist moves done under `app/Domains/*`. **Per-domain `routes.php` split is not done** — only Hifz loads `app/Domains/Hifz/routes.php`; `routes/web_localized.php` (136 lines) and `routes/web_public.php` (195 lines) remain central. **Deferred:** absorb route-file split into an early **S1 infrastructure slice** (before any URL-changing work); route-name snapshot tests now guard all major surfaces.

### 0.4 Contracts
- `SmsSenderInterface`, `PushSenderInterface` (null stub)
- `ImageProcessorInterface`, `MediaStorageInterface`
- `PaymentProviderInterface` in Finance\Contracts
- `DocumentRendererInterface` stub in Support

### 0.5 Architecture tests (CI-blocking)
| Rule | Enforcement |
|------|-------------|
| 1 — no cross-domain `Models\*` | Baseline **76 files** (`tests/Architecture/Baselines/cross_domain_models.php`) |
| 2 — cross-domain only via Contracts/DTOs/Events/Actions | Baseline **184 entries** (`cross_domain_non_contract.php`) |
| 3 — no concrete SMS/WebP/BML services cross-domain | Pest arch; `CheckoutController` BML concrete grandfathered until Finance contract |
| 4 — no `DB::` in domain controllers | Baseline **4 controllers** |
| 5 — Hifz only via Portal contracts | Baseline **3 files** (Portal dashboard + People Student/Teacher) |
| 6 — Commerce wallet tables | **Todo/skipped** until L4 |

Baselines may only **shrink** when violations are fixed; new violators fail CI. Regenerate after fixes: `python3 tests/Architecture/scripts/generate_baselines.py` (then remove fixed entries — never grow the list).

### 0.6 Cleanups
- Root deployment/readme MDs → `docs/legacy/`
- Duplicate OTP migration: **deferred** (verify prod schema before squash)

### 0.7 Definition of done
- `app/Models` and `app/Services` empty; `app/Http/Controllers` = `Controller.php` only
- **68** Pest tests green (feature + architecture + route snapshots)
- Inertia smoke route `/inertia-test` retained for production build verification
- Production deploy: **pending operator**

### Closure notes (no behavior change)
- Fixed stale namespaces on unused Legacy `HomeController` / `CourseController` (blocked arch scanner).
- Removed duplicate `Controller` import on `AdminUserController` (blocked route registration).

## Next (Phase S1)

- Student unification and course engine per `docs/S1_SPEC.md`
- Shrink architecture baselines as domains decouple; split centralized route files into per-domain `routes.php`
