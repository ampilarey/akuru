# ADR-003: Course taxonomy — hierarchical Subject + Audience + Level

## Context

Early spec drafts defined two overlapping flat lists — **Categories** (e.g. Language, Kids Courses, Professional Training) and **Subjects** (e.g. Arabic, Fiqh, Food Safety, Business) — both attached to courses. That model did not fit an Islamic institute: generic vocational categories (Food Safety, Customer Service) duplicated subject-like labels, while real browse needs (Nahw vs Sarf under Arabic, Hifz vs Tajweed under Quran) required a **tree**, not a second flat list.

Learners also differ by **audience** (Kids, School children, Adults) independently of subject and proficiency. The same *Arabic Nahw* content should be offerable as Level 1/Kids and Level 2/Adults without duplicating course templates — the spec already splits **Course** (reusable template) from **Offering** (scheduled batch).

## Decision

Adopt a **three-dimension taxonomy**, all **admin-managed** and **trilingual** (EN/DV/AR), with **seed examples only** — never hardcoded enums:

1. **Subject** — hierarchical (`course_subjects.parent_id` self-reference). Primary public browse axis. Replaces the old Category + Subject flat lists (spec §10.4–10.5).
2. **Audience** — flat (`audiences`: Kids, School children, Adults, All).
3. **Level** — flat (`course_levels`: Foundation, Beginner, Level 1, …).

**Where dimensions attach:**

| Dimension | Table | Notes |
|---|---|---|
| Subject | `courses.subject_id` | Leaf preferred; admin may allow non-leaf |
| Audience | `course_offerings.audience_id` | Per batch / delivery instance |
| Level | `course_offerings.level_id` | Per batch / delivery instance |

The **Courses engine core stays subject-ignorant** (WORKING_RULES §6): taxonomy is metadata and admin CRUD only; pedagogy stays in registered components/strategies.

## Consequences

- **Easier:** Clear public navigation (subject tree); Nahw/Sarf sub-topics; Kids vs Adults on the same template via offerings; one admin surface per dimension; RTL/trilingual labels on all taxonomy rows.
- **Migration:** Legacy `course_categories` and flat course–category links are replaced in Phase 1A (additive migration + backfill per live-data safety rule). `course_categories` is deprecated, not extended.
- **Schema naming:** `course_subjects` distinguishes course taxonomy from Academics school `subjects` (timetable/class assignments).
- **Admin UI:** Category CRUD goes away; Subject CRUD gains tree UI (`parent_id`); new Audience CRUD; Level CRUD unchanged in spirit.
- **Question bank / glossary:** May reference `course_subjects.id` for filtering; separate “category” fields on those tables (if any) are not course browse categories.
