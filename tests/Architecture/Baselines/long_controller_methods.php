<?php

// PHASE_0_CHECKLIST §0.5 rule 4, second half: "Controllers … have no
// `private function` business logic (heuristic: max method length) — enforce on
// NEW controllers only at first; legacy controllers get a baseline ignore-list
// that may only shrink."
//
// ROADMAP §6 lists "thin controllers" as a CI-blocking gate and SPEC §41 names
// it as one of four things the architecture suite must fail on. The DB-facade
// half shipped; this half never did.
//
// Threshold is 40 lines, which is the 95th percentile of the 1116 controller
// methods in the codebase (median 13, p90 31). It is a heuristic for "this
// method is doing work an Action should own", not a style rule.
//
// **The recorded number is the method's current length, and it may only go
// down.** A baselined method that grows fails too — otherwise a 45-line method
// could quietly become 300 and stay "known".
//
// Count: 58.

return [
    'app/Domains/Academics/Http/Controllers/SchoolRequestController.php::store' => 61,
    'app/Domains/Academics/Http/Controllers/TeacherRegisterController.php::show' => 55,
    'app/Domains/Academics/Http/Controllers/TeacherRegisterController.php::update' => 56,
    'app/Domains/Academics/Http/Controllers/TimetableBuilderController.php::index' => 54,
    'app/Domains/Academics/Http/Controllers/TimetableBuilderController.php::persist' => 42,
    'app/Domains/Admissions/Http/Controllers/AdminEnrollmentController.php::export' => 66,
    'app/Domains/Admissions/Http/Controllers/CheckoutController.php::start' => 95,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::checkoutLogin' => 58,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::continueForm' => 68,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::enroll' => 180,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::enrollOtpForm' => 47,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::enrollResendOtp' => 51,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::processEnrollmentFromSession' => 119,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::resume' => 44,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::retryPayment' => 52,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::setPassword' => 136,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::start' => 101,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::validateEnrollRequest' => 58,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::verify' => 66,
    'app/Domains/Courses/Components/Quran/Http/Controllers/QuranMushafController.php::importAyah' => 48,
    'app/Domains/Courses/Components/Quran/Http/Controllers/QuranPageController.php::show' => 51,
    'app/Domains/Courses/Components/Quran/Http/Controllers/TeachRecitationController.php::review' => 43,
    'app/Domains/Courses/Http/Controllers/CatalogQuestionController.php::index' => 41,
    'app/Domains/Courses/Http/Controllers/CatalogQuestionController.php::payload' => 48,
    'app/Domains/Courses/Http/Controllers/CatalogReviewController.php::export' => 80,
    'app/Domains/Courses/Http/Controllers/CourseOutlineController.php::storeBlock' => 65,
    'app/Domains/Courses/Http/Controllers/LearnAssessmentController.php::show' => 50,
    'app/Domains/ExamsGrades/Http/Controllers/GradebookController.php::export' => 41,
    'app/Domains/Identity/Http/Controllers/Auth/OtpLoginController.php::requestOtp' => 47,
    'app/Domains/Identity/Http/Controllers/Auth/PasswordOtpController.php::sendOtp' => 99,
    'app/Domains/Library/Http/Controllers/LibraryReaderController.php::read' => 46,
    'app/Domains/Notifications/Http/Controllers/SmsApiController.php::send' => 71,
    'app/Domains/People/Http/Controllers/StudentController.php::store' => 70,
    'app/Domains/People/Http/Controllers/StudentController.php::update' => 55,
    'app/Domains/People/Http/Controllers/StudentDirectoryController.php::show' => 115,
    'app/Domains/People/Http/Controllers/StudentDirectoryController.php::validatedStudent' => 57,
    'app/Domains/People/Http/Controllers/TeacherController.php::store' => 86,
    'app/Domains/People/Http/Controllers/TeacherController.php::update' => 77,
    'app/Domains/Portal/Http/Controllers/DashboardController.php::superAdminDashboard' => 51,
    'app/Domains/Portal/Http/Controllers/PortalAbsenceNoteController.php::store' => 43,
    'app/Domains/Portal/Http/Controllers/PortalAttendanceController.php::index' => 53,
    'app/Domains/Portal/Http/Controllers/PortalHomeController.php::export' => 76,
    'app/Domains/Portal/Http/Controllers/StaffOverviewController.php::export' => 53,
    'app/Domains/Settings/Http/Controllers/Admin/SettingsController.php::index' => 42,
    'app/Domains/Website/Http/Controllers/Admin/PublicSite/CourseController.php::store' => 53,
    'app/Domains/Website/Http/Controllers/PublicSite/CourseController.php::index' => 95,
    'app/Domains/Website/Http/Controllers/PublicSite/CourseController.php::show' => 55,
    'app/Domains/Website/Http/Controllers/PublicSite/EventController.php::downloadCalendar' => 41,
    'app/Domains/Website/Http/Controllers/PublicSite/EventController.php::index' => 96,
    'app/Domains/Website/Http/Controllers/PublicSite/EventController.php::register' => 42,
    'app/Domains/Website/Http/Controllers/PublicSite/GalleryController.php::index' => 76,
    'app/Domains/Website/Http/Controllers/PublicSite/GalleryController.php::search' => 54,
    'app/Domains/Website/Http/Controllers/PublicSite/HomeController.php::buildHomepageData' => 98,
    'app/Domains/Website/Http/Controllers/PublicSite/PostController.php::index' => 83,
    'app/Domains/Website/Http/Controllers/PublicSite/PostController.php::show' => 52,
    'app/Domains/Website/Http/Controllers/PublicSite/PrayerTimesController.php::resolve' => 43,
    'app/Domains/Website/Http/Controllers/PublicSite/SearchController.php::index' => 47,
    'app/Http/Controllers/Api/TestDeployWebhookController.php::__invoke' => 44,
];
