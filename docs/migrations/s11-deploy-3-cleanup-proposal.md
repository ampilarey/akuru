# S1 Deploy 3 cleanup proposal (not executed)

**Status:** proposal only. Do not run these migrations until an operator
confirms this document. ADR-021: no live data, so there is no ≥2 week
stability wait; cleanup is still a **separate confirmed slice**.

S1 Deploy 2 already switched reads to `students` via
`unified_student_id` (ADR-008). Dual-write still creates
`registration_students` / `student_guardians` on every new enrollment.

## Proposed drops / renames

| Step | Change | Why |
|---|---|---|
| 1 | Stop calling `DualWriteCourseStudentAction` and `LinkGuardianDualWriteAction` from `EnrollmentService` (and any other writers). New enrollments write `students` + `guardian_student` only. | Dual-write is the remaining write path into legacy tables. |
| 2 | Delete `EnsureLegacyStudentForUnifiedAction` (or make it a no-op that returns `students.id` and stops creating RS rows). Update `EnrollUnifiedStudentInOfferingAction` / `EnrollSelfLearningAction` to stop requiring a legacy RS id. | Today offering enrollment still dual-writes an RS row when the unified student has no `legacy_registration_student_id`. |
| 3 | Stop writing `course_enrollments.student_id` (legacy FK). Keep the column nullable until a later rename, or rename to `legacy_student_id` in this slice if no rows need it. Posted checkout `student_id` validation (`exists:registration_students,id`) must switch to `students.id`. | Blade/checkout still post the RS id (ADR-008 decision 5). |
| 4 | Stop writing `payments.student_id` (legacy). Reads already use `unified_student_id`. | Observer currently fills unified from legacy. |
| 5 | Drop table `student_guardians`. | Migrated to `guardian_student`. |
| 6 | Rename `registration_students` → `_archived_registration_students` (spec: archive, do not drop). | Historical course-registration rows. |
| 7 | Drop `students.legacy_registration_student_id`. | Transition key. |
| 8 | Remove `RegistrationStudent` model + policy + morph-map alias **or** point the model at the archived table for read-only forensics. | `@deprecated` since Deploy 2. |

## What breaks if this ships without the writers above

- **New course registration** (`EnrollmentService::enrollAdultSelf` / `enrollByParent`) creates an RS row then dual-writes. After step 1, those methods must create `students` directly.
- **Offering / self-learning enrollment** calls `EnsureLegacyStudentForUnifiedAction`, which **inserts** RS rows. Tests: `tests/Feature/Courses/*Enroll*`, `tests/Feature/Offerings/*`.
- **Checkout / adult profile** still post legacy `student_id`. Controllers under `Admissions` and `User::student` / `courseStudents()` fallbacks.
- **`ClearNonAdminUsers`** deletes `student_guardians` and `registration_students`. Rewrite to unified tables.
- **Local `local:clear-registration`** wipes `student_guardians`.
- Morph map alias `registration_student` if any polymorphic rows remain (should be none for this model).
- Architecture baselines that grandfather `RegistrationStudent` imports will shrink (good).

## Tests that must stay green (and which to add)

Existing:

- `tests/Feature/People/UnifiedStudentBackfillTest.php` — backfill + A2 matcher (keep; Deploy 3 does not remove the backfill action).
- `tests/Feature/People/UnifiedStudentReadSwitchTest.php` — Deploy 2 reads.
- `tests/Feature/People/UnificationRepresentativeSeederTest.php` — representative gate.
- Enrollment / checkout / offering enrollment feature tests.

Add in the cleanup slice (not now):

- Feature: new adult/parent enrollment creates **no** `registration_students` row and **no** `student_guardians` row.
- Feature: offering enrollment with a unified student that has no legacy key does **not** insert RS.
- Arch: `EnrollmentService` does not reference `DualWriteCourseStudentAction` / `RegistrationStudent`.
- Arch: no remaining `student_guardians` schema.
- Pest: `students:verify-unification` (strict, not `--representative`) still meaningful against leftover mappings *or* is retired once RS is archived.

**Must be rewritten in the same PR (found in the S4 audit, 2026-08-27):**

- `tests/Feature/BmlWebhookTest.php` imports `App\Domains\People\Models\RegistrationStudent` and builds its fixture from an RS row. It is green today only because dual-write is still on — **it breaks the moment `registration_students` is archived**. Rewrite it against unified `students` + `course_enrollments.unified_student_id` as part of this cleanup, not after. It is the one currently-green test that this slice is guaranteed to turn red, so budget for it rather than discovering it in CI.

## Deferred column drops — investigated 2026-08-27, mostly NOT safe

The S-track audit listed four "cheap additive deferrals to drop now that ADR-021
applies". Tracing them found that premise is wrong for three of the four. Do not
bundle these into the cleanup without reading this.

| Column | Verdict |
|---|---|
| `academic_years.terms` (json) | **Safe.** Only `AcademicYear`'s `$fillable` + cast reference it; every consumer reads the `terms` table. Drop the column and both model entries together. |
| `course_enrollments.term_key` | **NOT safe as a plain drop.** It is a MySQL **generated** column (`IFNULL(term_id, 0)`) and it backs `course_enrollments_student_course_term_unique (student_id, course_id, term_key)`. Dropping it silently removes the constraint that prevents duplicate enrollments. Any drop must replace that uniqueness first. |
| `course_enrollments.term_id` | **NOT safe. Still live.** `EnrollmentService` reads and writes it (lines ~272, ~312, ~368, ~417) and `term_key` is generated from it. |
| `students.emergency_contact_name` / `_phone` | **Unresolved.** Still in `Student::$fillable`. The replacement `EmergencyContact` model exists but is referenced only by `Student` — no action, controller or screen uses it, so the S1.1 emergency-contacts migration appears **incomplete**, not merely un-cleaned. Finish the replacement before dropping the columns. |

**Separate finding — `course_enrollments.unified_term_id` is a dead column.** It
is referenced only by its own migration and a schema-shape assertion in
`AcademicYearBackboneTest`. Nothing reads or writes it, so S1.5's intended
"switch to `unified_term_id`" never happened: `term_id` is still the live column
and `unified_term_id` is the unused one — the reverse of what STATUS implied.
Decide deliberately: either complete the switch, or drop `unified_term_id` and
record that `term_id` is the permanent column.

## What this does not do

- No Hifz behavior change. Hifz already keys on `students`.
- No Track B features.
- No production `--backfill`.
- Does **not** drop `course_enrollments.unified_student_id` (that is the live FK).

## Confirm to execute

Operator replies to execute this proposal as its own PR. Until then, dual-write stays on.
