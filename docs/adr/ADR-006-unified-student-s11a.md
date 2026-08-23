# ADR-006: Unified Student schema (S1.1a)

## Context

S1.1 unifies school `students` and course `registration_students` onto one People
record. This slice is **schema only** (Deploy 1 additive): no backfill, no read
switch. Guardian data today lives in two places: rich `parent_guardians` (keyed by
its own `id` + `user_id`) and `student_guardians` (pivot to registration students).

`CLAUDE.md` says migrations live in the owning domain. No domain `Database/migrations`
directories exist and nothing calls `loadMigrationsFrom`. Wiring that up would
reorder every future migration and belongs in the deferred S1 infra slice (with
per-domain `routes.php`).

Hifz already keeps string-backed enums under `app/Enums/Hifz` (central placement).
Student status is a People concern.

## Decision

1. **Guardian unification model:** `parent_guardians` remains the profile (own `id`
   PK; `user_id` FK). New pivot `guardian_student` is `guardian_id → parent_guardians.id`
   and `student_id → students.id`, with relationship + primary/pickup/financial flags
   and `unique(guardian_id, student_id)`. Old `student_guardians` and
   `student_parent` stay untouched until S1.1b / cleanup.

2. **Status enum placement:** `App\Domains\People\Enums\StudentStatus` (domain-owned),
   not `app/Enums`. The DB column is widened from MySQL enum to `VARCHAR` and cast
   to the PHP enum so existing values survive and later values (`prospective`,
   `withdrawn`) need no ALTER. Status is **not** mass-assignable;
   `ChangeStudentStatusAction` is the only writer and records `student_status_history`
   in the same transaction.

3. **Central migrations** for this slice (`database/migrations/`). People / Media /
   Courses tables ship together so ordering stays explicit. Domain folders stay
   unwired (infra slice later).

4. **`course_enrollments.unified_student_id`** is a nullable FK to `students`. The
   existing `student_id` (→ `registration_students`) is not renamed (final rename
   is the Phase 1B §3.4 offerings split). Owner today is Courses, not Offerings.

5. **`documents`** is a generic Media polymorphic table (`document` morph alias).
   No student-specific columns. HR (S5) and Library (L5) reuse it. Raw FQCNs must
   not be stored (`getMorphClass()` / morph map).

## Consequences

- S1.1b owns matching, backfill, guardian migration, and the verification script.
- `TeacherController` `status => active` is a Teacher write — left alone.
- RegistrationStudent read/create paths stay on the dual-write contract until Deploy 2.
- Architecture baselines must not grow from this slice.
