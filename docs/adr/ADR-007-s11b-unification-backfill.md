# ADR-007: Student unification backfill (S1.1b)

## Context

S1.1a shipped additive schema only. S1.1b is Deploy 1 backfill: map every
`registration_students` row onto `students`, migrate `student_guardians` onto
`guardian_student`, and fill `course_enrollments.unified_student_id`. Reads stay
on `RegistrationStudent` until Deploy 2.

S1.1a left `students.user_id` NOT NULL. Course registration already creates
children with `registration_students.user_id = null` (guardian login only).
Spec rule 2: a student need not have a user account. Creating unmatched rows
requires a nullable `students.user_id`.

`registration_students.national_id` / `passport` are encrypted; `students` stores
them in plaintext. Matching must decrypt via the People model.

People must not import Courses or Identity models (architecture baselines may
only shrink). Enrollment and user rows are read through `DB::table`.

Two `registration_students` can match one `students` row (duplicate course
profiles, or name+dob collision after the first RS is created). The legacy
column has a single slot.

## Decision

1. **Nullable `students.user_id`** (additive): drop any live FK on
   `user_id` (do not assume `students_user_id_foreign` — staging 2026-08-25
   had none), then `nullable()->change()` and re-add `nullOnDelete` if
   missing. Unmatched RS without a user create a student with `user_id`
   null. Not a user-status *change* — create uses `forceFill` (no
   `ChangeStudentStatusAction` / history row).

2. **Match order (no extra heuristics):** (a) `user_id`, else (b) decrypted
   `national_id`, else (c) exact first + last + dob. Empty keys are skipped.
   The first method that yields candidates wins. `>1` candidate → ambiguous,
   listed, not linked, no fallthrough.

3. **Collision:** first RS to claim a student wins `legacy_registration_student_id`.
   A later RS that matches the same student is unresolved. The column is
   **unique** (nullable unique; multiple NULLs allowed).

4. **Fill:** only empty `passport` / `national_id` on a matched student.
   Already-set values are not overwritten.

5. **Create:** status `active` if any `course_enrollments.status = active` for
   that RS, else `prospective`. School fields stay null. Null RS `gender`
   defaults to `male` (`students.gender` is still a NOT NULL enum).

6. **Guardians:** find/create `parent_guardians` by guardian `user_id` (user
   row via `DB::table`). Pivot copies `relationship` (null → `guardian`) and
   `is_primary`. `can_pickup` defaults true; `financial_responsible` false.
   Existing `guardian_student` pairs are skipped.

7. **Enrollments:** set `unified_student_id` only when null. Do not rename
   `student_id`.

8. **Gate:** `php artisan students:verify-unification` is DB-authoritative:
   every RS maps to exactly one student; every enrollment has
   `unified_student_id`; `student_guardians` count equals `guardian_student`
   rows on students that have a legacy key. Report file:
   `storage/app/s11b-student-unification-report.json`. Migration `down()` is a
   no-op. Command is not wired into auto-deploy (Deploy 2 operator gate).

9. **Central migrations** remain (`000002` schema, `000003` thin action
   caller). Domain folders still unwired.

## Consequences

- Deploy 2 (switch reads) is blocked until verify is green on a production-data
  copy. Ambiguous/colliding RS stay on `RegistrationStudent` until resolved.
- Architecture baselines must not grow: no `CourseEnrollment` / `User` imports
  in the People action.
- RegistrationStudent read/create paths stay dual-write until Deploy 2.
- Hifz is untouched.
