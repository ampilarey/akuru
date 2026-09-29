<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\FeatureTestNote;
use Illuminate\Support\Facades\DB;

/**
 * The whole platform as a testing checklist (System → Feature testing):
 * every main feature, by who uses it, with where to find it. The owner,
 * 2026-09-28, starting to test "each and every feature one by one": a
 * tester marks each one working, broken or blocked with a comment, and
 * every mark is kept (`feature_test_notes`), so the history can be read
 * later. Keys (`ft-…`) are stable: a result stays with its feature when
 * the wording changes. Until then this was a list of ticks in the shared
 * close-out store, with no comment and no way to say "broken".
 */
class ListFeatureWalkthroughAction
{
    /**
     * @return list<array{key: string, title: string, items: list<array{key: string, label: string, where: string}>}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => 'ft-signin', 'title' => 'Sign-in and accounts (everyone)', 'items' => [
                ['key' => 'ft-signin-1', 'label' => 'Sign in with email, phone or ID card and a password', 'where' => '/login'],
                ['key' => 'ft-signin-2', 'label' => 'Sign in with a one-time code (OTP) by SMS', 'where' => '/login'],
                ['key' => 'ft-signin-3', 'label' => 'An OTP-only account is asked on its home to set a password; setting it returns home', 'where' => '/account/set-password'],
                ['key' => 'ft-signin-4', 'label' => 'Each role lands on its own home (office, teacher day, family, shop, My learning, My account)', 'where' => '/dashboard'],
                ['key' => 'ft-signin-5', 'label' => 'A person with several roles switches workspace: header pill on a computer, Your accounts in the phone menu', 'where' => '/dashboard'],
                ['key' => 'ft-signin-6', 'label' => 'Menus hold only the current workspace; every tile on a home opens', 'where' => '/dashboard'],
                ['key' => 'ft-signin-7', 'label' => 'Language switch EN / DV / AR; Dhivehi and Arabic read right to left', 'where' => '/dv'],
                ['key' => 'ft-signin-8', 'label' => 'Alerts (notifications) and the profile page', 'where' => '/portal/notifications'],
                ['key' => 'ft-signin-9', 'label' => 'Linked accounts: prove another login and switch to it', 'where' => '/account/linked'],
                ['key' => 'ft-signin-10', 'label' => 'Two-step sign-in: turn it on with an authenticator app from My accounts; a password or OTP then asks for a code; a recovery code works once', 'where' => '/account/two-factor'],
            ]],
            ['key' => 'ft-website', 'title' => 'Public website (visitors)', 'items' => [
                ['key' => 'ft-website-1', 'label' => 'Home, courses, news, events, gallery, achievements, contact; old research and article addresses open the Digital Library', 'where' => '/en'],
                ['key' => 'ft-website-2', 'label' => 'Course page: outcomes, instructor, FAQ, seats and price', 'where' => '/en/courses'],
                ['key' => 'ft-website-3', 'label' => 'Register for a course for yourself: form, SMS code, confirm, complete', 'where' => '/en/courses'],
                ['key' => 'ft-website-4', 'label' => 'Register a child for a course; the child then waits for the office to verify the parent', 'where' => '/en/courses'],
                ['key' => 'ft-website-5', 'label' => 'Paid course: payment page, and access only after the bank confirms', 'where' => '/en/courses'],
                ['key' => 'ft-website-6', 'label' => 'Admissions / apply form and the contact form reach the office', 'where' => '/en/apply'],
                ['key' => 'ft-website-7', 'label' => 'Prayer times page and widget; daily content (ayah)', 'where' => '/en/prayer-times'],
                ['key' => 'ft-website-8', 'label' => 'Certificate check by QR code', 'where' => '/verify/certificates/'],
                ['key' => 'ft-website-9', 'label' => 'Header: the Digital Library menu (Books, Articles, Research, Authors, Gift cards) on a computer and in the phone menu; About holds the institute pages', 'where' => '/en'],
                ['key' => 'ft-website-10', 'label' => 'Site search finds courses, library books, articles and research, news and events, in EN, DV and AR', 'where' => '/en/search'],
                ['key' => 'ft-website-11', 'label' => 'Events: open an event from the list, register from its page, add it to a calendar; gallery albums open from the list', 'where' => '/en/events'],
                ['key' => 'ft-website-12', 'label' => 'The website in Dhivehi and Arabic reads right to left: logo on the right, menus, tiles, footer and the phone bar mirrored, "see all" arrows pointing the reading way; English unchanged', 'where' => '/dv'],
            ]],
            ['key' => 'ft-institute', 'title' => 'Institute (System admin)', 'items' => [
                ['key' => 'ft-institute-1', 'label' => 'Website pages: create, edit, preview, publish', 'where' => '/admin/public-site/pages'],
                // R4 (RESEARCH_ARTICLES_PLAN): the news editor.
                ['key' => 'ft-institute-11', 'label' => 'Website news: write with a cover, schedule or publish, pin and feature, categories, CSV', 'where' => '/admin/public-site/news'],
                ['key' => 'ft-institute-2', 'label' => 'Website courses, daily content calendar and approval queue (research is written in the Digital Library since R2)', 'where' => '/admin/public-site/courses'],
                ['key' => 'ft-institute-3', 'label' => 'Leads, the enrolment funnel and daily subscriptions, each with CSV', 'where' => '/admin/public-site/leads'],
                ['key' => 'ft-institute-4', 'label' => 'Instructors shown on the website', 'where' => '/admin/instructors'],
                ['key' => 'ft-institute-5', 'label' => 'Prayer times: islands, groups, broadcasts, import', 'where' => '/admin/prayer-times/islands'],
                ['key' => 'ft-institute-6', 'label' => 'Manage users: create, set roles, activate and deactivate', 'where' => '/admin/users'],
                ['key' => 'ft-institute-7', 'label' => 'System settings and clear caches', 'where' => '/admin/settings'],
                ['key' => 'ft-institute-8', 'label' => 'Commerce: gift cards, discounts, wallets', 'where' => '/admin/commerce'],
                ['key' => 'ft-institute-9', 'label' => 'Payments list and refunds', 'where' => '/admin/enrollments/payments'],
                ['key' => 'ft-institute-10', 'label' => 'Translations: edit Dhivehi and Arabic wording', 'where' => '/admin/translations'],
            ]],
            ['key' => 'ft-office', 'title' => 'School office (Educational admin, Dean)', 'items' => [
                ['key' => 'ft-office-1', 'label' => 'Course enrolments: approve, reject, set access dates, record a payment', 'where' => '/admin/enrollments'],
                ['key' => 'ft-office-2', 'label' => 'Academic years and terms; rooms; periods', 'where' => '/academics/years'],
                ['key' => 'ft-office-3', 'label' => 'Timetable builder refuses a double-booked teacher or room', 'where' => '/academics/timetable'],
                ['key' => 'ft-office-4', 'label' => 'Room bookings, calendar and holidays, events, clubs', 'where' => '/academics/calendar'],
                ['key' => 'ft-office-5', 'label' => 'Students: add, edit, guardians, verify a parent link', 'where' => '/people/students'],
                ['key' => 'ft-office-6', 'label' => 'Staff, custom fields, sensitive information', 'where' => '/people/staff'],
                ['key' => 'ft-office-7', 'label' => 'Registers and the whole-school attendance report', 'where' => '/academics/registers'],
                ['key' => 'ft-office-8', 'label' => 'Absences, absence notes review, attendance policy', 'where' => '/academics/attendance/absences'],
                ['key' => 'ft-office-9', 'label' => 'Behaviour records', 'where' => '/academics/behavior'],
                ['key' => 'ft-office-10', 'label' => 'Pick-up, gate and gate cards', 'where' => '/academics/pickup'],
                ['key' => 'ft-office-11', 'label' => 'Student work showcase; lost and found', 'where' => '/academics/work'],
                ['key' => 'ft-office-12', 'label' => 'Notices and substitutions', 'where' => '/announcements'],
                ['key' => 'ft-office-13', 'label' => 'Forms and requests (leave, family requests)', 'where' => '/academics/requests'],
                ['key' => 'ft-office-14', 'label' => 'Parent–teacher meeting slots', 'where' => '/academics/meetings'],
            ]],
            ['key' => 'ft-exams', 'title' => 'Exams and report cards', 'items' => [
                ['key' => 'ft-exams-1', 'label' => 'Exam schedule, exam types, weights', 'where' => '/exams/schedule'],
                ['key' => 'ft-exams-2', 'label' => 'Gradebook: enter marks', 'where' => '/exams/gradebook'],
                ['key' => 'ft-exams-3', 'label' => 'Grading scales, competencies, standards', 'where' => '/exams/scales'],
                ['key' => 'ft-exams-4', 'label' => 'Report card templates and report cards (issue, download)', 'where' => '/exams/report-cards'],
                ['key' => 'ft-exams-5', 'label' => 'Awards', 'where' => '/exams/awards'],
            ]],
            ['key' => 'ft-finance', 'title' => 'School finance', 'items' => [
                ['key' => 'ft-finance-1', 'label' => 'Fee items and fee structures', 'where' => '/finance/fee-structures'],
                ['key' => 'ft-finance-2', 'label' => 'Invoices: generate, issue, reminders', 'where' => '/finance/invoices'],
                ['key' => 'ft-finance-3', 'label' => 'Arrears, payment plans, adjustments', 'where' => '/finance/arrears'],
                ['key' => 'ft-finance-4', 'label' => 'Manual receipt and collections', 'where' => '/finance/receipts/manual'],
                ['key' => 'ft-finance-5', 'label' => 'Bank reconciliation: import a statement and match', 'where' => '/finance/reconciliation'],
            ]],
            ['key' => 'ft-hr', 'title' => 'HR and payroll', 'items' => [
                ['key' => 'ft-hr-1', 'label' => 'Staff attendance and reports', 'where' => '/hr/attendance'],
                ['key' => 'ft-hr-2', 'label' => 'Leave types and balances; a staff member applies for leave', 'where' => '/hr/leave-balances'],
                ['key' => 'ft-hr-3', 'label' => 'Contracts and compliance documents', 'where' => '/hr/contracts'],
                ['key' => 'ft-hr-4', 'label' => 'Job postings and applications; onboarding', 'where' => '/hr/postings'],
                ['key' => 'ft-hr-5', 'label' => 'Appraisals, lesson observations, training (CPD)', 'where' => '/hr/appraisals'],
                ['key' => 'ft-hr-6', 'label' => 'Payroll run and payslips', 'where' => '/hr/payroll'],
            ]],
            ['key' => 'ft-teacher', 'title' => 'Teacher', 'items' => [
                ['key' => 'ft-teacher-1', 'label' => 'My day: today\'s lessons and unfilled registers', 'where' => '/portal/teacher'],
                ['key' => 'ft-teacher-2', 'label' => 'Take a register (attendance) for a class', 'where' => '/academics/registers/today'],
                ['key' => 'ft-teacher-3', 'label' => 'Lesson plans, materials, homework', 'where' => '/academics/plans'],
                ['key' => 'ft-teacher-4', 'label' => 'Record behaviour', 'where' => '/academics/behavior'],
                ['key' => 'ft-teacher-5', 'label' => 'Teaching schedule and my meetings', 'where' => '/teach/schedule'],
                ['key' => 'ft-teacher-6', 'label' => 'Qur\'an recitations queue', 'where' => '/teach/recitations'],
                ['key' => 'ft-teacher-7', 'label' => 'Message a parent or a class, with a poll', 'where' => '/portal/messages'],
                ['key' => 'ft-teacher-8', 'label' => 'Own record: check-in, leave, appraisals, payslips', 'where' => '/portal/staff-check-in'],
            ]],
            ['key' => 'ft-catalog', 'title' => 'Course catalogue (Course creator)', 'items' => [
                ['key' => 'ft-catalog-1', 'label' => 'Create a course: modules, lessons, content blocks, media', 'where' => '/catalog/courses'],
                ['key' => 'ft-catalog-2', 'label' => 'Activities and assessments with the question bank', 'where' => '/catalog/questions'],
                ['key' => 'ft-catalog-3', 'label' => 'Intakes (offerings): seats, price, sessions', 'where' => '/catalog/offerings'],
                ['key' => 'ft-catalog-4', 'label' => 'Review queue and publishing', 'where' => '/catalog/reviews'],
                ['key' => 'ft-catalog-5', 'label' => 'Certificates: template and issue', 'where' => '/catalog/certificates'],
                ['key' => 'ft-catalog-6', 'label' => 'Reports and completions', 'where' => '/catalog/reports'],
                ['key' => 'ft-catalog-7', 'label' => 'Arabic skills and Qur\'an course tools', 'where' => '/catalog/arabic'],
                ['key' => 'ft-catalog-8', 'label' => 'Subjects, glossary, audiences, levels', 'where' => '/catalog/subjects'],
            ]],
            ['key' => 'ft-parent', 'title' => 'Parent (Family)', 'items' => [
                ['key' => 'ft-parent-1', 'label' => 'Family home: each child\'s attendance, results, invoices, course progress, Hifz', 'where' => '/portal/home'],
                ['key' => 'ft-parent-2', 'label' => 'My children and a child\'s library reading', 'where' => '/portal/children'],
                ['key' => 'ft-parent-3', 'label' => 'Homework and school calendar', 'where' => '/portal/homework'],
                ['key' => 'ft-parent-4', 'label' => 'Pay fees online', 'where' => '/portal/invoices'],
                ['key' => 'ft-parent-5', 'label' => 'Send an absence note', 'where' => '/portal/absence-notes'],
                ['key' => 'ft-parent-6', 'label' => 'Event sign-up with a trip fee', 'where' => '/portal/events'],
                ['key' => 'ft-parent-7', 'label' => 'Collecting my child: pick-up PIN and request', 'where' => '/portal/pickup'],
                ['key' => 'ft-parent-8', 'label' => 'Arrivals and departures', 'where' => '/portal/movements'],
                ['key' => 'ft-parent-9', 'label' => 'Results, report cards, attendance, behaviour, awards, performance', 'where' => '/portal/exams'],
                ['key' => 'ft-parent-10', 'label' => 'Book a parent–teacher meeting', 'where' => '/portal/meetings'],
                ['key' => 'ft-parent-11', 'label' => 'Lost property and my child\'s work', 'where' => '/portal/found-items'],
                ['key' => 'ft-parent-12', 'label' => 'Messages, notices and forms from the school', 'where' => '/portal/messages'],
                ['key' => 'ft-parent-13', 'label' => 'My enrolments with receipts', 'where' => '/my-enrollments'],
            ]],
            ['key' => 'ft-pupil', 'title' => 'Pupil (Student)', 'items' => [
                ['key' => 'ft-pupil-1', 'label' => 'Learn: my courses, lessons, activities', 'where' => '/learn'],
                ['key' => 'ft-pupil-2', 'label' => 'Assessments, results and retakes', 'where' => '/learn'],
                ['key' => 'ft-pupil-3', 'label' => 'Timetable / schedule', 'where' => '/learn/schedule'],
                ['key' => 'ft-pupil-4', 'label' => 'Homework: tick as done', 'where' => '/portal/homework'],
                ['key' => 'ft-pupil-5', 'label' => 'Results, attendance, report cards', 'where' => '/portal/exams'],
                ['key' => 'ft-pupil-6', 'label' => 'Arabic report and Qur\'an recitation', 'where' => '/learn/quran'],
                ['key' => 'ft-pupil-7', 'label' => 'Certificates', 'where' => '/learn'],
            ]],
            ['key' => 'ft-learner', 'title' => 'Adult learner and My account (no role)', 'items' => [
                ['key' => 'ft-learner-1', 'label' => 'My learning: courses under way, enrolments waiting for payment or the office', 'where' => '/learn'],
                ['key' => 'ft-learner-2', 'label' => 'Browse courses and enrol; pay by wallet or card', 'where' => '/learn/catalog'],
                ['key' => 'ft-learner-3', 'label' => 'My account: children waiting for the office, latest enrolments, tiles', 'where' => '/my-account'],
                ['key' => 'ft-learner-4', 'label' => 'My enrolments: every enrolment and payment, receipt, CSV', 'where' => '/my-enrollments'],
            ]],
            ['key' => 'ft-hifz', 'title' => 'Hifz (Qur\'an memorisation)', 'items' => [
                ['key' => 'ft-hifz-1', 'label' => 'Programmes and enrolments', 'where' => '/hifz'],
                ['key' => 'ft-hifz-2', 'label' => 'Record progress; the dean, supervisor, teacher, parent and student dashboards', 'where' => '/hifz'],
                ['key' => 'ft-hifz-3', 'label' => 'Milestones: recommend, review, approve or reject', 'where' => '/hifz'],
                ['key' => 'ft-hifz-4', 'label' => 'The six Hifz reports with CSV', 'where' => '/hifz'],
            ]],
            ['key' => 'ft-library', 'title' => 'Digital Library', 'items' => [
                ['key' => 'ft-library-1', 'label' => 'Shelf, filters and search; author pages', 'where' => '/library'],
                ['key' => 'ft-library-2', 'label' => 'Protected reader: PDF and article, in-book search, reading time', 'where' => '/library'],
                ['key' => 'ft-library-3', 'label' => 'Buy an item with the wallet; My library', 'where' => '/my-library'],
                ['key' => 'ft-library-4', 'label' => 'Gift cards: buy, redeem; promotions and offers', 'where' => '/library'],
                ['key' => 'ft-library-5', 'label' => 'Writer: apply, upload a work, send for review, author page', 'where' => '/write'],
                ['key' => 'ft-library-6', 'label' => 'Reviewer: declare no conflict of interest, read the paper, recommend accept, revise or reject; due dates shown', 'where' => '/review'],
                ['key' => 'ft-library-7', 'label' => 'Office: approve and publish, campaigns, settings, insights, fraud log', 'where' => '/admin/library'],
                ['key' => 'ft-library-8', 'label' => 'Research: the author chooses read online, download or both; teacher authors link to their profile; year filter on the research shelf', 'where' => '/library?content_type=research'],
                ['key' => 'ft-library-9', 'label' => 'Peer review is required: research cannot be published without the required accepts; a revision starts a new round; the writer sees where review stands', 'where' => '/admin/library'],
                ['key' => 'ft-library-10', 'label' => 'Reviewer pool: add and remove reviewers, due dates and reminders, overdue reports, CSV', 'where' => '/admin/library/reviewers'],
                ['key' => 'ft-library-11', 'label' => 'Authors list on the shelf, and the website\'s old research papers imported into the library', 'where' => '/library#authors'],
            ]],
            ['key' => 'ft-bookstore', 'title' => 'Bookstore', 'items' => [
                ['key' => 'ft-bookstore-1', 'label' => 'Shop, categories, product page, search; the Bookstore menu in the header (Shops, Deals, School book lists, Categories, My orders, Sell on Akuru, Shop owners: sign in)', 'where' => '/shop'],
                ['key' => 'ft-bookstore-2', 'label' => 'Cart and checkout: card, wallet, bank slip, cash on delivery', 'where' => '/shop/cart'],
                ['key' => 'ft-bookstore-3', 'label' => 'My orders: tracking, cancel, returns; wishlist; bulk quotes', 'where' => '/my-orders'],
                ['key' => 'ft-bookstore-4', 'label' => 'Vendor: agreement, products, bulk edit, CSV import and export', 'where' => '/vendor'],
                ['key' => 'ft-bookstore-5', 'label' => 'Vendor: stock, orders, print and ship', 'where' => '/vendor/orders'],
                ['key' => 'ft-bookstore-6', 'label' => 'Vendor: storefront designer and sections', 'where' => '/vendor/storefront'],
                ['key' => 'ft-bookstore-7', 'label' => 'Vendor: money and payouts, reviews, discount codes, insights', 'where' => '/vendor/money'],
                ['key' => 'ft-bookstore-8', 'label' => 'Vendor: delivery methods, staff members, own domain', 'where' => '/vendor'],
                ['key' => 'ft-bookstore-9', 'label' => 'Office: vendor applications, moderation, bank slips, payouts, themes', 'where' => '/admin/bookshop'],
                ['key' => 'ft-bookstore-10', 'label' => 'Every open shop is listed on the shop page (Opening soon when it has nothing yet); on a phone the filters fold under Filter and sort', 'where' => '/shop'],
                ['key' => 'ft-bookstore-11', 'label' => 'Timed sales: a shop sets % off until a date on its product form; the card shows the badge and a countdown, Deals lists them, and the cart and checkout charge the sale price until it ends', 'where' => '/shop/deals'],
                ['key' => 'ft-bookstore-12', 'label' => 'School book lists: a shop marks a collection as a school\'s list for a grade with quantities; parents find it under School book lists and add the whole list to the cart in one tap', 'where' => '/shop#book-lists'],
                ['key' => 'ft-bookstore-13', 'label' => 'Buy again: from My orders or an order\'s page, one tap puts its items back in the cart at today\'s prices; what is no longer sold is named', 'where' => '/my-orders'],
                ['key' => 'ft-bookstore-14', 'label' => 'Questions and answers: a signed-in customer asks on a product page; the shop answers on its Reviews page; the answer shows on the product page; the office can hide one', 'where' => '/vendor/reviews'],
                ['key' => 'ft-bookstore-15', 'label' => 'Save for later: a cart line is set aside under the cart (not counted or charged) and moved back in when wanted; a guest\'s saved lines come along at sign-in', 'where' => '/shop/cart'],
                ['key' => 'ft-bookstore-16', 'label' => 'Helpful review votes: a signed-in customer marks someone else\'s review helpful (and can take it back); the most helpful reviews come first', 'where' => '/shop'],
                ['key' => 'ft-bookstore-17', 'label' => 'Brands: Shop by brand on the store\'s front, a brand\'s page with its products from every shop, a brand filter, and the product page links its brand', 'where' => '/shop'],
                ['key' => 'ft-bookstore-18', 'label' => 'Compare: "Compare" on up to four product pages, then see them side by side (price, shop, stars, stock, details); fits a phone', 'where' => '/shop/compare'],
                ['key' => 'ft-bookstore-19', 'label' => 'Track an order without signing in: order number + phone shows status, steps, tracking and items — never the address or money; a wrong phone shows nothing', 'where' => '/shop/track'],
                ['key' => 'ft-bookstore-20', 'label' => 'The catalogue API: /api/v1/bookstore/products (with the listing\'s filters), /products/{slug}, /shops, /categories — JSON, in EN/DV/AR, nothing that is not for sale', 'where' => '/api/v1/bookstore/products'],
                ['key' => 'ft-bookstore-21', 'label' => 'Rewards (office, off by default): set the share, smallest order and cap, turn on; a delivered order shows what it will earn, and the daily job pays it into the wallet after the return window', 'where' => '/admin/bookshop'],
                ['key' => 'ft-bookstore-22', 'label' => 'Referral credit (office, off by default): set both amounts and the smallest first order, turn on; a customer\'s invite link on My orders; a friend\'s checkout says what a first order earns; both credited after it is delivered and past its return window', 'where' => '/my-orders'],
                ['key' => 'ft-bookstore-23', 'label' => 'Pause a shop (office, Edit on the shop\'s row → Paused): its page and products leave the store, its owner keeps the portal with a banner and finishes the orders already paid; Active brings it back', 'where' => '/admin/bookshop'],
            ]],
            ['key' => 'ft-money', 'title' => 'Money checks', 'items' => [
                ['key' => 'ft-money-1', 'label' => 'A payment grants access only after the bank confirms (not on return to the site)', 'where' => '/admin/enrollments/payments'],
                ['key' => 'ft-money-2', 'label' => 'Receipts open for the payer and show the right lines', 'where' => '/my-enrollments'],
                ['key' => 'ft-money-3', 'label' => 'Refund a payment; the ledger shows the reversal', 'where' => '/admin/enrollments/payments'],
                ['key' => 'ft-money-4', 'label' => 'Wallet top-up and spending', 'where' => '/my-wallet'],
                ['key' => 'ft-money-5', 'label' => 'Gift card purchase and redemption; a discount never reduces a gift card', 'where' => '/library'],
            ]],
            ['key' => 'ft-phone', 'title' => 'Phone and app', 'items' => [
                ['key' => 'ft-phone-1', 'label' => 'Every screen above at phone width in EN, DV and AR: nothing runs off the side', 'where' => '/dashboard'],
                ['key' => 'ft-phone-2', 'label' => 'Installed app opens on the person\'s home (after the app is rebuilt)', 'where' => '/dashboard'],
                ['key' => 'ft-phone-3', 'label' => 'Push notifications (after the Firebase and Apple keys are set)', 'where' => '/portal/notifications'],
            ]],
        ];
    }

    /**
     * @return list<string>
     */
    public static function itemKeys(): array
    {
        $keys = [];
        foreach (self::definitions() as $section) {
            foreach ($section['items'] as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    /**
     * The list with each feature's latest result and every earlier one.
     *
     * @return array{sections: mixed, results: array<string, array{status: string, comment: ?string, by: ?string, at: string}>, history: array<string, list<array{status: string, comment: ?string, by: ?string, at: string}>>, counts: array{works: int, broken: int, blocked: int, untested: int}, total: int}
     */
    public function execute(): array
    {
        $keys = self::itemKeys();
        $notes = FeatureTestNote::query()->whereIn('item_key', $keys)->orderByDesc('id')->get();
        $names = DB::table('users')->whereIn('id', $notes->pluck('user_id')->filter()->unique()->all())->pluck('name', 'id');

        $history = [];
        foreach ($notes as $note) {
            $history[$note->item_key][] = [
                'status' => (string) $note->status,
                'comment' => $note->comment,
                'by' => $note->user_id !== null ? ($names[$note->user_id] ?? null) : null,
                'at' => $note->created_at?->format('Y-m-d H:i') ?? '',
            ];
        }
        $results = array_map(fn (array $entries): array => $entries[0], $history);

        $counts = ['works' => 0, 'broken' => 0, 'blocked' => 0];
        foreach ($results as $result) {
            $counts[$result['status']] = ($counts[$result['status']] ?? 0) + 1;
        }
        $counts['untested'] = count($keys) - count($results);

        return [
            'sections' => self::definitions(),
            'results' => $results,
            'history' => $history,
            'counts' => $counts,
            'total' => count($keys),
        ];
    }
}
