# Akuru Platform — Working Rules (read first, every session)

This file governs every AI-assisted coding session in this repo. It encodes the §57 Build Strategy discipline and the architecture rules. If an instruction conflicts with this file, stop and ask.

## Document map (read before coding)

| Doc | Use |
|---|---|
| `docs/ROADMAP.md` | architecture, domain map, phase order, all decisions |
| `docs/SPEC.md` | course engine requirements (Phases 1A–5, §51 Arabic, §52 Quran, §57 build strategy) |
| `docs/LIBRARY_PLAN.md` | Knowledge Library (L-track) requirements |
| `docs/PHASE_0_CHECKLIST.md`, `docs/S1_SPEC.md`, `docs/S2_SPEC.md`, `docs/S3_SPEC.md`, `docs/W1_SPEC.md` | per-phase build specs |
| `STATUS.md` | what is done, in progress, next — UPDATE AFTER EVERY SLICE |
| `docs/adr/` | architecture decision records |

## The 12 rules

1. **One slice at a time.** Work only on the current slice from the current phase spec. No future-phase implementation "while you're there." If a slice reveals a needed change elsewhere, note it in STATUS.md and stop.
2. **Tests ship with the slice.** A slice without its tests is not done. Architecture tests must stay green at every commit.
3. **Domain boundaries are law.** A domain never imports another domain's `Models\*`. Cross-domain = that domain's `Contracts`/`DTOs`/`Events`/`Actions` only. Components under `Courses/Components/*` never import each other and never import engine models directly.
4. **No SDKs in domain logic.** Payments, SMS, storage, video, AI — always behind a domain-owned interface with a container binding.
5. **Thin controllers.** authorize → validate into DTO → call Action → return response. Business logic lives in Actions/Services.
6. **The engine core stays subject-ignorant.** Never add `if (course_type === ...)` or any Hifz/Arabic/subject-specific branch inside Courses/Offerings/Progress core. Subject behavior = registered component or strategy.
7. **Hifz is frozen** until its §2b migration phase: namespace/route changes only; no behavior change, no refactor, no dashboard redesign. Freeze is **scope discipline** (do not refactor while moving), not production-safety — no live Hifz users yet (ADR-021).
8. **No AI code in Phases 0–1B.** Pronunciation AI only via `Domains/Pronunciation` contracts, feature-flagged, and only in its designated phases. Everything must work with AI off.
9. **Live-data safety: 3 deploys.** Additive migration + backfill → switch reads → cleanup. Never drop/rename a populated column in the deploy that stops using it. Verification scripts are gates, not suggestions. **Until first real use** (first real student, payment, or Hifz user on any deployment — none exist as of 2026-08-25; `test.akuru.edu.mv` is synthetic-only) dual-write windows and “≥2 weeks stable” waits are optional. Additive migrations remain good practice. Rule 9 reactivates in full at that trigger (ADR-021). The unification gate until then is the seeded representative dataset, not a production dump.
10. **Time-scoped data carries the backbone.** Any new table recording something that happens in time (attendance, marks, invoices, sessions) carries `academic_year_id` (and `term_id` where relevant).
11. **Single sources of truth.** One student record (People). One Quran dataset (existing `surahs`/`quran_*` tables — never create parallel ones). One wallet/gift-card/discount system (Commerce). One document renderer interface. One gradebook contract.
12. **Money rules.** Access to paid anything depends on BML **webhook** confirmation, never the return URL. Wallet/ledger tables are append-only (reversals, not deletes/updates). Gift cards = payment method; discounts = price reduction; discounts never apply to gift-card purchases.

## Conventions

- New UI = Inertia + React. No new Blade screens (existing Blade keeps running until replaced). All screens trilingual-ready (EN/DV/AR) and RTL-safe; every listing gets CSV export.
- Pest for new tests; `tests/Architecture/` is CI-blocking.
- Enums: string-backed PHP enums. Money: integer laari or decimal(10,2) consistently per ADR. Dates: app timezone Indian/Maldives.
- Migrations live in the owning domain's `Database/migrations`.
- Commit per slice-step; conventional commit messages; CI (pint + pest + arch) must pass before merge.
- **Merge gates (mechanical, per ROADMAP §4):** one slice per PR; `main` is branch-protected — required CI check pre-merge, no direct pushes, no bot self-merge; a spec-mandated verification script's captured output must be in STATUS.md **before** the deploy it gates. A gate whose evidence isn't recorded has not run.
- **Every new polymorphic or pseudo-polymorphic column** registers its aliases in `config/morph-map.php` in the same slice (ADR-005); FQCNs never enter the database.

## Definition of done (every slice)

code + tests green + arch tests green + STATUS.md updated + (if a decision was made) ADR written + no out-of-scope files touched.
