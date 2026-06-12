# ADR-001: Domain map and single-institute tenancy

## Context

Phase 0 restructures the flat Laravel app into a modular monolith under `app/Domains/`. Before moving code (Phase 0 §0.3) and before S1 migrations, we must fix the domain boundaries and decide whether Akuru runs as a single institute or multi-branch tenancy.

The repo today has a `School` model and scattered `school_id` columns, but no full tenancy layer. The platform roadmap (`docs/ROADMAP.md` §2) defines 20 top-level domains plus `app/Support/` for shared primitives.

## Decision

1. **Domain map:** Adopt the roadmap domain layout with one `ServiceProvider` per domain under `app/Domains/{Name}/Providers/`, registered in `bootstrap/providers.php`. Cross-domain rules: no foreign domain `Models\*` imports; cross-domain access via Contracts, DTOs, Events, and Actions only (enforced by arch tests in Phase 0 §0.5).

2. **Tenancy:** **Single-institute** for the foreseeable build. Akuru Institute is one organization on one deployment. Keep existing `school_id` / `School` references as future-safe columns where they already exist; do **not** add tenancy middleware, branch-scoped policies, or per-branch data isolation in Phases 0–S1 unless requirements change.

3. **Hifz:** Moved into the `Hifz` domain namespace in Phase 0 §0.3 only — no behavior or UI changes until the dedicated §2b migration phase.

## Consequences

- Phase 0 §0.3 moves are namespace/route updates into the domain map above, one commit per move group, with URLs unchanged.
- S1 and later phases implement features inside the owning domain without duplicating Identity, People, Finance, or Notifications.
- If multi-branch is required later, tenancy becomes a deliberate ADR with additive migrations and a phased read switch — not an emergent refactor.
- `app/Support/` holds shared DTO bases and helpers; it is not a domain and may be imported by any domain.
