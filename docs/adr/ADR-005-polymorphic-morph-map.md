# ADR-005: Polymorphic morph map after Phase 0 domain move

## Context

Phase 0 moved every Eloquent model from `app/Models/*` into `app/Domains/*/Models/*`
(and a few into `Academics/Legacy/Models`). Polymorphic and class-name columns in the
database still hold fully-qualified names from **two eras**:

- **Pre-Phase-0:** `App\Models\*` (and `App\Notifications\*` for `notifications.type`)
- **Post-Phase-0, pre-morph-map (staging since 2026-06-13):** `App\Domains\*\Models\*`
  — Phase 0 code wrote the new FQCNs because no morph map was registered yet

Affected columns:

- `model_has_roles.model_type` / `model_has_permissions.model_type`
- `payments.payable_type`
- `notifications.notifiable_type`
- `notifications.type` (notification class name, not a morph column)

Without a morph map and a data rewrite that covers **both** eras, production users
lose roles (admin lockout), `Payment::payable` breaks, and queued/stored
notifications reference dead classes. A backfill that only rewrites `App\Models\*`
reports a false green on staging while every post-June row still holds a raw FQCN.

Additionally, `model_has_roles` / `model_has_permissions` include `model_type` in
their composite primary key. A user granted a role before the move and again after
yields two rows that both normalize to `user` — a mid-migration PK violation unless
duplicates are collapsed first.

`CLAUDE.md` prefers migrations in the owning domain, but no domain migration directories
exist yet and this rewrite spans Identity, Finance, and Notifications.

## Decision

1. **Morph map (alias → current FQCN)** lives in `config/morph-map.php` and covers
   every domain model (including Academics/Legacy). Aliases reuse
   `config/domain-models.php` where those classes appear (`user`, `course`,
   `school`, `instructor`, `admission_application`).

2. **Non-enforcing first.** `MorphMapServiceProvider` calls `Relation::morphMap()`
   only — not `enforceMorphMap()`. Enforcement is a one-line follow-up after the
   map is verified in production, so a missed model cannot hard-crash the cutover.

3. **Provider placement.** `App\Providers\MorphMapServiceProvider`, registered first
   in `bootstrap/providers.php`. Not a domain provider — importing 19 domains'
   models from one domain provider would grow architecture baselines 1 and 2.

4. **Rewrite logic owns the migration.** `App\Support\MorphMap` owns:
   - `legacyMorphRewrites()` — `App\Models\*` → alias
   - `morphRewrites()` — legacy map **plus** `array_flip(config('morph-map'))` so
     current domain FQCNs rewrite to the same aliases and cannot drift from the map
   - `legacyNotificationRewrites()` — three `App\Notifications\*` → domain FQCNs
   - `backfill()` — collapse composite-key duplicates, then chunked rewrite, inside
     one transaction; writes a collapse report for the verify command

5. **`notifications.type` is FQCN → FQCN, not aliased.** Laravel stores the
   notification class name and does not consult the morph map when writing it.
   Aliasing would invert the bug. Those three classes stay out of
   `config/morph-map.php`.

6. **Central migrations** in `database/migrations/`. `down()` is a documented no-op —
   restoring deleted FQCNs would corrupt post-deploy alias rows. A second thin
   migration re-calls `backfill()` so environments that already ran the first
   (Models-only) rewrite still pick up mixed-era + collapse behavior.

7. **Verification gate** `php artisan morph-map:verify` (rule 9):
   - **Morph columns:** any value containing a backslash fails (no-FQCN invariant —
     stronger than matching two known prefixes; survives domain renames).
   - **`notifications.type`:** fail on `App\Notifications\*` or unexpected
     `App\Domains\*\Notifications\*` classes outside the three known-good targets.
   - Print collapse counts/details from the backfill report.

## Consequences

- Deploy requires drain queue → `artisan down` → migrate → verify → permission/cache
  reset → `artisan up` (atomic cutover; see STATUS.md runbook).
- **Staging is a real mixed-era test** — pre-June `App\Models\*` rows and post-June
  `App\Domains\*` rows must both become aliases; collapse counts from that run are
  part of the go/no-go evidence.
- S1 polymorphic tables (e.g. Media `documents`) must register aliases in the same
  morph map — never store raw FQCNs. The no-backslash gate will catch mistakes.
- Flip to `Relation::enforceMorphMap()` only after staging/production verification.
- Architecture baselines must not grow from this change.
