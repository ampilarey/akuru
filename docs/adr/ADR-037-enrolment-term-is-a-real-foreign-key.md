# ADR-037: The enrolment term is a real foreign key, and `status` alone says which year is active

**Date:** 2026-09-22 · **Status:** accepted · **Phase:** S1.5 (finishing) · **Supersedes:** the `unified_term_id` half of the 2026-08-23 backbone migration

## Context

The S1 audit (STATUS §5eq–§5er) found two rule-9 switches in S1.5 that had
stopped after deploy 1.

**Terms.** `docs/S1_SPEC.md` asked for `course_enrollments.term_id` to become
a real FK to the new `terms` table and for the generated `term_key` column to
be dropped. The 2026-08-23 migration instead added `unified_term_id` (FK →
`terms`), backfilled it once, and left `term_id` as it was — the additive
deploy, with the read/write switch to follow. It never followed. A month
later nothing in the application read or wrote `unified_term_id`; registration
still wrote `term_id` from a hidden form field that was never validated, into
an `int(10) unsigned` column that referenced nothing.

**Active year.** `academic_years` carried both the pre-S1 `is_current` flag and
the S1.5 `status`. `ActivateAcademicYearAction` enforced "exactly one active
year" on `status`; five readers (the staff overview, meeting-slot options,
event registration, and two register actions' fallbacks) asked `is_current`.
The actions wrote both, so the default seed stayed consistent — but
`FeaturePackDemoSeeder` set only the flag, and any raw write to either column
made registers and exams disagree about which year it was.

## Decision

1. **The FK goes on `term_id` itself.** Orphan values take the Legacy term the
   earlier backfill chose (via `unified_term_id`) or become NULL; the column is
   widened to `bigint unsigned` to match `terms.id`; the constraint is
   `ON DELETE RESTRICT` — a term that still has enrolments cannot be deleted,
   and deleting a term can never delete an enrolment. (`SET NULL` was the
   first choice; MariaDB refuses a nulling or cascading FK on a column a
   generated column is built from, error 1901, and on reflection the
   restriction is the better rule: the enrolment is the record of what
   happened in that term.)
   `unified_term_id` is dropped. Since nothing ever read it, "stop reading,
   then drop" collapses to one deploy.
2. **`term_key` stays**, against the spec's "drop generated term_key". It is
   `IFNULL(term_id, 0)` and it is the only reason the unique key
   `(student_id, course_id, term_key)` catches a second course-only enrolment:
   MySQL treats NULLs as distinct in a unique index, so a key on `term_id`
   alone would admit two NULL-term rows for the same pupil and course. #397's
   `CreateOrReviveEnrollmentAction` depends on exactly this. The spec's
   instruction was written before that behaviour was understood; the column
   is a mechanism, not leftover.
3. **Registration validates `term_id`** (`nullable|integer|exists:terms,id`)
   at both entry points, so an invalid term is a form error rather than a
   foreign-key exception in the OTP step.
4. **`status` is the single answer to "which year is active".** Every reader
   asks `status`; `ListAcademicYearsAction` still emits `is_current` for the
   screens that read it, but derives it from `status`. The column stays for
   one more deploy because Blade screens and a month of fixtures write it,
   and a `saving` hook on the model keeps the two in step whichever a writer
   touched: setting `status` moves the flag; raising the flag alone means
   active; lowering it demotes an active year to upcoming and leaves a closed
   year closed.
5. **`academic_years.terms` (JSON) is dropped.** The `terms` table replaced it
   on 2026-08-23; nothing has read it since.

## Consequences

- One column, one FK, one unique key for enrolment terms; the schema now says
  what the spec meant, minus the one instruction that would have removed a
  real guard.
- The invariant "exactly one active year" is enforced and read on the same
  column. A future cleanup drops `is_current` once nothing writes it; the
  hook makes that a deletion rather than a migration.
- The migration is forward-only (rule 9). It drops and recreates `term_key`
  and its unique key because MySQL will not `MODIFY` a column a generated
  column depends on; a collision on the rebuild stops the deploy loudly
  rather than rewriting a row.
- `docs/S1_SPEC.md` §S1.5 "drop generated `term_key`" is superseded by this
  record.
