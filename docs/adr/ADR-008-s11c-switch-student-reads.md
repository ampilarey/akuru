# ADR-008: Switch student reads (S1.1c / Deploy 2)

## Context

S1.1a/b added unified `students` rows and `course_enrollments.unified_student_id`.
Deploy 2 switches **reads** to `students` while dual-write stays on
`registration_students` (and `student_guardians`) until Deploy 3.

`CourseEnrollment::student()` and `Payment::student()` previously pointed at
`RegistrationStudent`. Views and mail use `full_name`, `dob`, `first_name`,
and guardian `name`. Architecture baselines may only shrink: Courses/Finance
must not gain new People `Models\*` imports.

Staging/production-data verify (`students:verify-unification`) is still the
operator gate for messy rows. Dual-write plus a save-time observer covers new
enrollments even if some legacy rows were unresolved.

## Decision

1. **`CourseEnrollment::student()` / `Payment::student()`** resolve
   `config('domain-models.student')` via `unified_student_id`. Legacy FK stays
   on `student_id` as `legacyStudent()`. No People model `use` in those classes.

2. **`payments.unified_student_id`** is additive (nullable FK) and backfilled
   from `students.legacy_registration_student_id`. Saving a payment or
   enrollment with only the legacy id fills the unified id when a mapping
   exists.

3. **Dual-write (People Actions):** `DualWriteCourseStudentAction` mirrors each
   written `RegistrationStudent` onto `students`. `LinkGuardianDualWriteAction`
   writes both pivots. `EnrollmentService` calls these; it still creates RS
   rows (dual-write ON).

4. **Read compatibility shims on `Student`:** `dob` aliases `date_of_birth`;
   `age()` matches the old RS helper. `ParentGuardian::name` aliases
   `full_name` so enrollment Blade/mail keep working.

5. **Controller reads:** adult profile and checkout use `User::student`;
   parent child lists use `User::courseStudents()` (`guardian_student`) with a
   fallback to `guardianStudents` if the unified pivot is empty. Posted
   `student_id` remains the **legacy RS id** so enrollment unique keys and
   validation (`exists:registration_students,id`) do not change this slice.

6. **`RegistrationStudent` is `@deprecated`.** Not deleted. Deploy 3 drops
   dual-write.

## Consequences

- Architecture rule 2 shrinks (Checkout/Payment/PaymentService/CourseEnrollment
  no longer import `RegistrationStudent`). Password OTP drops off rule 1.
- New enrollments always have a `students` row and `unified_student_id`.
- Hifz is untouched (already keyed on `students`).
- Deploy 3 still owns dropping `student_guardians`, renaming
  `course_enrollments.student_id`, and archiving `registration_students`.
