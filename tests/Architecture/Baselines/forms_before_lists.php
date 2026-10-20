<?php

// docs/MOBILE_FIRST.md rule 4 — see tests/Architecture/RecordsBeforeFormsTest.php.
//
// Inertia pages whose posting form comes before their list without a
// PhoneFold, each kept on purpose: the form is the page, a single button,
// or an editor a row loads into. A page added here says why. Count: 24
// (2026-10-20, MF7).

return [
    'resources/js/Pages/Academics/Classes/Show.jsx' => 'a class page: the class-teacher setting is one select on the class itself',
    'resources/js/Pages/Academics/Registers/Show.jsx' => 'the register is the form',
    'resources/js/Pages/Circulation/Title.jsx' => 'a title page: adding copies is one field on the title itself',
    'resources/js/Pages/Courses/Catalog/Certificates.jsx' => 'the form is the editor a template row loads into',
    'resources/js/Pages/Courses/Catalog/Glossary.jsx' => 'the form is the editor a term row loads into',
    'resources/js/Pages/Courses/Catalog/Outline.jsx' => 'the outline editor',
    'resources/js/Pages/Finance/Receipts/Manual.jsx' => 'recording a receipt is the page',
    'resources/js/Pages/HR/Compliance/Index.jsx' => 'one button: send due notices',
    'resources/js/Pages/HR/Payroll/Index.jsx' => 'one button: run payroll',
    'resources/js/Pages/HR/Recruitment/Onboarding.jsx' => 'one button: open a checklist',
    'resources/js/Pages/HR/Settings/Index.jsx' => 'a settings page',
    'resources/js/Pages/Hifz/Program.jsx' => 'a programme page: assigning its supervisor is one select',
    'resources/js/Pages/Identity/UserRoles.jsx' => 'assigning roles is the page',
    'resources/js/Pages/Library/BlogDesign.jsx' => 'the page designer',
    'resources/js/Pages/Library/Reviewers.jsx' => 'one field: add a reviewer',
    'resources/js/Pages/Library/Settings.jsx' => 'a settings page',
    'resources/js/Pages/Offerings/Catalog/Attendance.jsx' => 'marking attendance is the page',
    'resources/js/Pages/People/Staff/Show.jsx' => 'a staff record: its employment form is the record',
    'resources/js/Pages/Portal/Messages/Create.jsx' => 'writing a message is the page',
    'resources/js/Pages/Portal/Notifications.jsx' => 'notification preferences, a settings form',
    'resources/js/Pages/Portal/StaffCheckIn.jsx' => 'one button: check in today',
    'resources/js/Pages/Website/CourseCategories.jsx' => 'one field: add a category',
    'resources/js/Pages/Website/Events/Show.jsx' => 'an event editor',
    'resources/js/Pages/Website/NewsCategories.jsx' => 'one field: add a category',
];
