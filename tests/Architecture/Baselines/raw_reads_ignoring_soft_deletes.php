<?php

/**
 * Raw `DB::table()` reads of a soft-deleting table that deliberately do not
 * filter on `deleted_at`, and why each one wants the deleted rows.
 *
 * See `tests/Architecture/SoftDeletesSurviveRawReadsTest.php`. **This list may
 * only shrink** — the test fails on a stale entry as loudly as on a new
 * violation.
 *
 * Most of these are one idea: **a historical record has to keep naming the
 * thing it was about.** A certificate issued for a course that was later
 * deleted still has to print that course's name, or the certificate becomes
 * unverifiable — which is worse than showing the name of something retired.
 *
 * @return array<string, string>
 */
return [
    // --- Verification counts: a deleted row was still migrated -------------
    'app/Domains/Academics/Actions/MigrateLegacyAssessmentsAction.php:51' => 'Counts what the legacy migration has already moved. An assessment deleted after migration was still migrated, and excluding it would make the gate under-report and the migration look incomplete forever.',
    'app/Domains/Academics/Actions/MigrateLegacyAssessmentsAction.php:63' => 'As above, for assignments.',

    // --- Historical records keep their names -------------------------------
    'app/Domains/Courses/Actions/VerifyIssuedCertificateAction.php:40' => 'Public certificate verification. A certificate for a deleted course must still verify and still name the course, or deleting a course silently invalidates every certificate ever issued for it.',
    'app/Domains/Courses/Actions/VerifyIssuedCertificateAction.php:43' => 'As above, for the offering title.',
    'app/Domains/Courses/Actions/VerifyIssuedCertificateAction.php:45' => 'As above, for the template name.',
    'app/Domains/Courses/Actions/ListIssuedCertificatesAction.php:30' => 'The admin list of issued certificates. Same reason: the row is a record of something that happened, and it has to keep naming it.',
    'app/Domains/Courses/Actions/ListIssuedCertificatesAction.php:33' => 'As above, for the offering title.',
    'app/Domains/Courses/Actions/ListIssuedCertificatesAction.php:36' => 'As above, for the template name.',
    'app/Domains/Courses/Actions/IssueCertificateAction.php:84' => 'The offering title stamped onto a certificate at the moment of issue.',
    'app/Domains/Courses/Actions/BuildEnrollmentReportRowsAction.php:33' => 'Offering titles for an enrolment report. An enrolment on a retired offering still belongs in the report, under the name it had.',

    // --- Deliberately about deleted things ---------------------------------
    'app/Domains/Courses/Actions/ListDeletedCoursesAction.php:52' => 'The screen listing deleted courses, counting the enrolments each one holds. Filtering deleted rows out of a report about deleted rows would empty it. **Worth a second look one day:** the count includes soft-deleted *enrolments* too, which may overstate what restoring the course would bring back.',

    // --- Backfills that must see every row ---------------------------------
    'app/Domains/People/Actions/UnifyStudentsAction.php:496' => 'The S1 backfill repointing `course_enrollments` at unified students. A soft-deleted enrolment still carries a foreign key and still has to be repointed, or the cleanup deploy finds rows pointing at a table that is going away.',
    'app/Domains/People/Actions/UnifyStudentsAction.php:542' => 'The verification count of enrolments with no `unified_student_id`. Counting deleted rows too is the safe direction: a gate that ignores them could pass while unmapped rows remain.',
];
