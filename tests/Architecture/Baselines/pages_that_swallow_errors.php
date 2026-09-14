<?php

// Inertia pages that submit a form and never mention `errors`, so a refusal
// is invisible to the person who pressed the button.
//
// Found by driving the forms rather than reading them: a non-numeric Hours on
// the CPD screen produced no row, no message, and the typed values still
// sitting in the boxes. See tests/Architecture/FormErrorsAreShownTest.php.
//
// **This list may only shrink.** A page that learns to show its errors must be
// deleted from here, and the test fails on a stale entry as loudly as on a new
// violation. Of the 92 pages that submit a form, these are the ones that cannot
// report a refusal.
//
// Count: 26.

return [
    'resources/js/Pages/Academics/AbsenceNotes/Index.jsx',
    'resources/js/Pages/Academics/Attendance/Daily.jsx',
    'resources/js/Pages/Academics/Promotion/Wizard.jsx',
    'resources/js/Pages/Academics/Requests/Index.jsx',
    'resources/js/Pages/Commerce/Admin.jsx',
    'resources/js/Pages/Courses/Catalog/ArabicReference.jsx',
    'resources/js/Pages/Courses/Catalog/Assessments.jsx',
    'resources/js/Pages/Courses/Catalog/Reviews.jsx',
    'resources/js/Pages/Courses/Taxonomy/Audiences.jsx',
    'resources/js/Pages/Courses/Taxonomy/Levels.jsx',
    'resources/js/Pages/Courses/Teach/QuranAssignments.jsx',
    'resources/js/Pages/Courses/Teach/QuranMilestones.jsx',
    'resources/js/Pages/Courses/Teach/QuranSessionSheet.jsx',
    'resources/js/Pages/Finance/Receipts/Manual.jsx',
    'resources/js/Pages/HR/Leave/Balances.jsx',
    'resources/js/Pages/HR/Recruitment/Applications.jsx',
    'resources/js/Pages/HR/Recruitment/Postings.jsx',
    'resources/js/Pages/Library/Admin.jsx',
    'resources/js/Pages/Offerings/Catalog/Attendance.jsx',
    'resources/js/Pages/Offerings/Catalog/Sessions.jsx',
    'resources/js/Pages/People/Staff/Index.jsx',
    'resources/js/Pages/People/Staff/Show.jsx',
    'resources/js/Pages/Portal/Appraisals.jsx',
    'resources/js/Pages/Portal/Homework.jsx',
    'resources/js/Pages/Portal/Meetings.jsx',
    'resources/js/Pages/Pronunciation/Admin.jsx',
];
