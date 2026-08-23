# ADR-010: Promotion semantics

## Context

S1.5 adds `PromoteStudentsAction` as the only bulk path from one academic year
to the next. Spec S1.5 asked for ADR-003; that number is already used for
course taxonomy. This is the promotion ADR.

Promotion is irreversible in the UI (correction = manual admin edit + status
action). A dry-run report is therefore mandatory before commit.

## Decision

1. **Default outcome is promote** when a source class is mapped to a target
   class. Per-student overrides: `repeat`, `leave`, `graduate`.

2. **Promote:** close the source `class_student` row (`promoted`), open an
   active row in the mapped target class, dual-write `students.class_id`.

3. **Repeat:** leave the current `class_student` row active (same class). No
   status change.

4. **Leave:** close the roster row (`left`), clear `students.class_id`, set
   student status to `withdrawn` via `ChangeStudentStatusAction`.

5. **Graduate:** close the roster row (`promoted`), clear `students.class_id`,
   set student status to `graduated` via the same People action.

6. **Dry-run** computes the same report and writes nothing. Commit is rejected
   unless a dry-run for that source/target year pair was recorded in the last
   hour.

7. `course_enrollments.term_id` / generated `term_key` stay. New
   `unified_term_id` is the real FK to `terms` (same 3-deploy pattern as
   student unification). `academic_years.terms` json is not dropped in this
   deploy.

## Consequences

- Academics calls People Actions only (no People model imports).
- S2 attendance/exams/invoices key off `class_student` + `academic_year_id`
  / `terms.id`.
