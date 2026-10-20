<?php

// CLAUDE.md "Phone-first" and docs/MOBILE_FIRST.md — see
// tests/Architecture/PhoneFirstStylesTest.php.
//
// Inertia pages with a table that is neither `table-stack` (app.css) nor
// `data-phone-scroll`: on a phone it stays squeezed columns instead of a
// list of cards. Counted per table since MF4. The list may
// only SHRINK: give the table `table-stack` and each cell its `data-label`,
// then delete the line. Count: 48 (2026-10-20, after MF5: courses and exams).

return [
    'resources/js/Pages/Bookshop/CommissionInvoice.jsx',
    'resources/js/Pages/Bookshop/Vendor.jsx',
    'resources/js/Pages/Bookshop/VendorOrderPrint.jsx',
    'resources/js/Pages/Circulation/Index.jsx',
    'resources/js/Pages/Circulation/Title.jsx',
    'resources/js/Pages/Finance/Adjustments/Index.jsx',
    'resources/js/Pages/Finance/Arrears/Index.jsx',
    'resources/js/Pages/Finance/BankStatements/Index.jsx',
    'resources/js/Pages/Finance/Collections/Index.jsx',
    'resources/js/Pages/Finance/FeeItems/Index.jsx',
    'resources/js/Pages/Finance/FeeStructures/Index.jsx',
    'resources/js/Pages/Finance/Invoices/Index.jsx',
    'resources/js/Pages/Finance/PaymentPlans/Index.jsx',
    'resources/js/Pages/Finance/Reconciliation/Index.jsx',
    'resources/js/Pages/Forms/Results.jsx',
    'resources/js/Pages/HR/Attendance/Index.jsx',
    'resources/js/Pages/HR/Attendance/Reports.jsx',
    'resources/js/Pages/HR/Compliance/Index.jsx',
    'resources/js/Pages/HR/Contracts/Index.jsx',
    'resources/js/Pages/HR/Leave/Balances.jsx',
    'resources/js/Pages/HR/Leave/Types.jsx',
    'resources/js/Pages/HR/Payroll/Index.jsx',
    'resources/js/Pages/HR/Performance/Appraisals.jsx',
    'resources/js/Pages/HR/Performance/Cpd.jsx',
    'resources/js/Pages/HR/Performance/Observations.jsx',
    'resources/js/Pages/HR/Recruitment/Applications.jsx',
    'resources/js/Pages/HR/Recruitment/Onboarding.jsx',
    'resources/js/Pages/HR/Recruitment/Postings.jsx',
    'resources/js/Pages/Library/Write.jsx',
    'resources/js/Pages/Offerings/Catalog/Index.jsx',
    'resources/js/Pages/Offerings/Catalog/Sessions.jsx',
    'resources/js/Pages/People/CustomFields/Index.jsx',
    'resources/js/Pages/People/Staff/Index.jsx',
    'resources/js/Pages/People/Staff/Show.jsx',
    'resources/js/Pages/People/Students/Index.jsx',
    'resources/js/Pages/People/Students/Show.jsx',
    'resources/js/Pages/PrayerTimes/Broadcasts.jsx',
    'resources/js/Pages/PrayerTimes/Groups.jsx',
    'resources/js/Pages/Pronunciation/Admin.jsx',
    'resources/js/Pages/Pronunciation/Practice.jsx',
    'resources/js/Pages/Pronunciation/Teach.jsx',
    'resources/js/Pages/Settings/Translations.jsx',
    'resources/js/Pages/Website/DailyContent.jsx',
    'resources/js/Pages/Website/DailyContentQueue.jsx',
    'resources/js/Pages/Website/DailySubscriptions.jsx',
    'resources/js/Pages/Website/Events/Index.jsx',
    'resources/js/Pages/Website/Events/Show.jsx',
    'resources/js/Pages/Website/NewsCategories.jsx',
];
