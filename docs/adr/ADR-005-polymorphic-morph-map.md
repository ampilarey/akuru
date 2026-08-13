# ADR-005: Polymorphic morph map after Phase 0 domain move

## Context

Phase 0 moved every Eloquent model from `app/Models/*` into `app/Domains/*/Models/*`
(and a few into `Academics/Legacy/Models`). Polymorphic and class-name columns in the
database still hold the old fully-qualified names:

- `model_has_roles.model_type` / `model_has_permissions.model_type`
- `payments.payable_type`
- `notifications.notifiable_type`
- `notifications.type` (notification class name, not a morph column)

Without a morph map and a data rewrite, production users lose roles (admin lockout),
`Payment::payable` breaks, and queued/stored notifications reference dead classes.
Staging already sits in this broken state after the Phase 0 deploy.

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

4. **Rewrite logic owns the migration.** `App\Support\MorphMap` holds the
   legacy-FQCN → alias table (and the three notification FQCN → FQCN pairs) plus
   `backfill()`. The migration is a thin caller so Pest can exercise the same path
   against seeded legacy rows (migrate:fresh alone never hits real data).

5. **`notifications.type` is FQCN → FQCN, not aliased.** Laravel stores the
   notification class name and does not consult the morph map when writing it.
   Aliasing would invert the bug. Those three classes stay out of
   `config/morph-map.php`.

6. **Central migration** in `database/migrations/`. `down()` is a documented no-op —
   restoring deleted FQCNs would corrupt post-deploy alias rows.

7. **Verification gate** `php artisan morph-map:verify` fails loudly on any remaining
   `App\Models\%` / `App\Notifications\%` values (rule 9).

## Consequences

- Deploy requires drain queue → `artisan down` → migrate → verify → permission/cache
  reset → `artisan up` (atomic cutover; see STATUS.md runbook).
- S1 polymorphic tables (e.g. Media `documents`) must register aliases in the same
  morph map — never store raw FQCNs.
- Flip to `Relation::enforceMorphMap()` only after staging/production verification.
- Architecture baselines must not grow from this change.
