# 1A Spec — Course engine core

**Phase:** 1A (after S1; can run in parallel with S2–S5). Source of truth: `docs/SPEC.md` §46.1–46.2 and §57.2.
**Domain:** Courses (engine). Website public `courses` stay Blade until replaced. No 1B offerings, no Arabic/Hifz/AI.

Slice order is strict. Do not start the next slice with failing tests.

## Slice 1A.1 — Auth, roles, users, settings (already satisfied)

Phase 0 + S1 already shipped authentication, Spatie roles, user management, Settings, rate limiting, and the Inertia `AppShell`. No new code in this slice.

## Slice 1A.2 — Taxonomy + course CRUD + status workflow

**New `course_subjects`:** hierarchical `parent_id`, trilingual names, slug, sort_order, active. Seed examples only (Quran/Arabic/… tree). Distinct from Academics school `subjects`.
**New `audiences` and `course_levels`:** flat, trilingual, admin-managed. They attach to **offerings in 1B** — CRUD exists now so the dimensions are ready; do not put them on `courses`.
**Courses table (additive):** `subject_id` nullable FK, `workflow_status` (`draft` → `in_review` → `published` → `archived`), `course_type` default `general`, `created_by`. Existing marketing `status` (`open`/`closed`/`upcoming`) is untouched (rule 9). `course_category_id` becomes nullable; `course_categories` is deprecated, not extended (ADR-003).

Invalid workflow transitions rejected. `courses.publish` is required for In Review → Published. Engine admin is Inertia under `/catalog/*`.

## Slice 1A.3 — Modules, lessons, immutable revisions (done)

`course_modules`, `lessons`, `content_blocks` (draft), `lesson_revisions`.
Publish copies an ordered JSON snapshot (ADR-017). The player reads
`current_revision_id` only. Draft edit/delete/reorder must not mutate
a published snapshot. 1B offering pinning will reuse revision ids.

## Slice 1A.4 — Text / Rich Text / Instruction blocks + builder/player (done)

Validated `text`, `rich_text`, and `instruction` only. Direction is a
block setting (`ltr`/`rtl`/`auto`), not a block type. Media types are
rejected until 1A.5. Player renders the published snapshot.

## Slice 1A.5 — Media pipeline + image/audio/video/PDF blocks

## Slice 1A.6 — Self-learning enrollment, student dashboard, progress

## Slice 1A.7 — Parent-child polish + architecture tests

**Out of scope until later slices/phases:** offerings, delivery modes, remaining block types, certificates, payments, Arabic, Hifz, AI.
