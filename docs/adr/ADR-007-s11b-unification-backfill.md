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
   had none), `nullable()->change()`, **null orphan `user_id`s** that do
   not exist in `users` (1452 otherwise), then re-add `nullOnDelete` if
   missing. Unmatched RS without a user create a student with `user_id`
   null. Not a user-status *change* — create uses `forceFill` (no
   `ChangeStudentStatusAction` / history row). Orphan nulling is not
   reversible (`down()` cannot restore deleted user ids).

2. **Match order** (supersedes `docs/S1_SPEC.md` Deploy 1 step 1 / line 49):
   (a) `user_id`, else (b) decrypted `national_id`, else (c) exact first + last
   + dob. Empty keys are skipped. The first method that yields **usable**
   candidates wins. `>1` candidate → ambiguous, listed, not linked, no
   fallthrough.

   **`national_id` is unusable** (treated as empty; fall through to name+dob)
   when it is blank/whitespace, a **placeholder** from
   `config/unification.php` (`national_id_placeholders`, case-insensitive;
   operators may append via `UNIFICATION_NATIONAL_ID_PLACEHOLDERS`), or the
   same normalized value appears on **more than one** `registration_students`
   row or **more than one** `students` row. Duplicate IDs are test-data and
   live-data noise; they must not win over name+dob.

   **Contradiction falls through to name+dob, then stops:** if a *usable*
   `national_id` matches exactly one student but that student's first+last+dob
   does **not** match the RS (complete name+dob key), **do not** link the ID
   hit. Continue to name+dob. A unique name+dob candidate is the match
   (staging RS 22: ID pointed at student 8, name+dob at student 5 — attach 5).
   If name+dob is empty or ambiguous after that skip, record **ambiguous**
   (`reason: name_dob_contradiction`) and do **not** create a new student.
   Corroboration beats any single field. Collisions stay unguessed.

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

- Deploy 2 (switch reads) is gated on the **seeded representative dataset** until first real use (ADR-021). The production-data copy requirement reactivates the day real students/payments/Hifz users exist. Ambiguous/colliding RS stay unguessed until resolved.
- Architecture baselines must not grow: no `CourseEnrollment` / `User` imports
  in the People action.
- RegistrationStudent read/create paths stay dual-write until Deploy 2.
- Hifz is untouched.
