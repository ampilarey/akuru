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
// Threshold is 36 **lines of code** — comments stripped, blank lines not
// counted — which is the 95th percentile of the 1116 controller methods in the
// codebase (median 11, p90 26). It is a heuristic for "this method is doing
// work an Action should own", not a style rule.
//
// The first version of this gate counted the raw span and failed a change whose
// only addition was a two-line comment. See the test docblock: a check that
// cannot tell documentation from instruction punishes writing the explanation
// down.
//
// **The recorded number is the method's current code length, and it may only go
// down.** A baselined method that grows fails too — otherwise a 40-line method
// could quietly become 300 and stay "known".
//
// Count: 49.

return [
    'app/Domains/Academics/Http/Controllers/TeacherRegisterController.php::show' => 45,
    'app/Domains/Academics/Http/Controllers/TeacherRegisterController.php::update' => 48,
    'app/Domains/Academics/Http/Controllers/TimetableBuilderController.php::index' => 48,
    'app/Domains/Academics/Http/Controllers/TimetableBuilderController.php::persist' => 39,
    'app/Domains/Admissions/Http/Controllers/AdminEnrollmentController.php::export' => 56,
    'app/Domains/Admissions/Http/Controllers/CheckoutController.php::start' => 95,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::checkoutLogin' => 41,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::continueForm' => 58,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::enroll' => 146,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::enrollResendOtp' => 41,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::processEnrollmentFromSession' => 89,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::retryPayment' => 39,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::setPassword' => 106,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::start' => 81,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::validateEnrollRequest' => 50,
    'app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php::verify' => 49,
    'app/Domains/Courses/Components/Quran/Http/Controllers/QuranMushafController.php::importAyah' => 43,
    'app/Domains/Courses/Components/Quran/Http/Controllers/QuranPageController.php::show' => 47,
    'app/Domains/Courses/Http/Controllers/CatalogQuestionController.php::payload' => 37,
    'app/Domains/Courses/Http/Controllers/CatalogReviewController.php::export' => 79,
    'app/Domains/Courses/Http/Controllers/CourseOutlineController.php::storeBlock' => 48,
    'app/Domains/ExamsGrades/Http/Controllers/GradebookController.php::export' => 39,
    'app/Domains/Identity/Http/Controllers/Auth/OtpLoginController.php::requestOtp' => 37,
    'app/Domains/Identity/Http/Controllers/Auth/PasswordOtpController.php::sendOtp' => 66,
    'app/Domains/Notifications/Http/Controllers/SmsApiController.php::send' => 60,
    'app/Domains/People/Http/Controllers/StudentDirectoryController.php::show' => 76,
    'app/Domains/People/Http/Controllers/StudentDirectoryController.php::validatedStudent' => 56,
    'app/Domains/Portal/Http/Controllers/PortalAttendanceController.php::index' => 38,
    'app/Domains/Portal/Http/Controllers/PortalHomeController.php::export' => 75,
    'app/Domains/Portal/Http/Controllers/PortalPerformanceController.php::export' => 37,
    'app/Domains/Website/Http/Controllers/Admin/PublicSite/CourseController.php::store' => 39,
    'app/Domains/Website/Http/Controllers/Admin/PublicSite/DailySubscriptionController.php::export' => 37,
    'app/Domains/Website/Http/Controllers/Admin/PublicSite/FunnelController.php::export' => 39,
    'app/Domains/Website/Http/Controllers/PublicSite/CourseController.php::index' => 73,
    'app/Domains/Website/Http/Controllers/PublicSite/CourseController.php::show' => 40,
    'app/Domains/Website/Http/Controllers/PublicSite/EventController.php::index' => 73,
    'app/Domains/Website/Http/Controllers/PublicSite/GalleryController.php::index' => 60,
    'app/Domains/Website/Http/Controllers/PublicSite/GalleryController.php::search' => 44,
    'app/Domains/Website/Http/Controllers/PublicSite/HomeController.php::buildHomepageData' => 78,
    'app/Domains/Website/Http/Controllers/PublicSite/PostController.php::index' => 61,
    'app/Domains/Website/Http/Controllers/PublicSite/PrayerTimesController.php::resolve' => 37,
    'app/Domains/Website/Http/Controllers/PublicSite/SearchController.php::index' => 41,
];
