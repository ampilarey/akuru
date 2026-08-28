<?php

namespace App\Domains\Settings\Actions;

use App\Domains\Settings\Models\OperatorCheck;
use Illuminate\Support\Facades\DB;

/**
 * The whole platform as a testable inventory — every shipped feature
 * area with what to try and where, distilled from STATUS.md's phase
 * table. Same shared tick store as the close-out checklist
 * (operator_checklist_checks); keys are namespaced `fw-…` and stable.
 * A tick means "a person walked this in a browser and it worked" —
 * the USABLE column of STATUS.md, made clickable.
 */
class ListFeatureWalkthroughAction
{
    /**
     * @return list<array{key: string, title: string, items: list<array{key: string, label: string, where: string}>}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => 'fw-public', 'title' => 'Public website (guest)', 'items' => [
                ['key' => 'fw-pub1', 'label' => 'Homepage trust signals: accreditation line, years, students count, partner logos', 'where' => '/en'],
                ['key' => 'fw-pub2', 'label' => 'Open Courses urgency: seats-left badges, deadline countdown, early-bird strike-through', 'where' => '/en/courses'],
                ['key' => 'fw-pub3', 'label' => 'Course page: outcomes above description, testimonials, instructor qualifications, FAQ', 'where' => '/en/courses/{slug}'],
                ['key' => 'fw-pub4', 'label' => 'Mobile sticky CTA: price + Register + WhatsApp; syllabus lead magnet form', 'where' => '/en/courses/{slug}'],
                ['key' => 'fw-pub5', 'label' => 'Full course shows waitlist form; submission lands in leads + inquiries', 'where' => '/en/courses/{slug}'],
                ['key' => 'fw-pub6', 'label' => 'SEO view-source: Course/Organization/FAQPage JSON-LD, hreflang en/dv/ar, sitemap.xml', 'where' => '/sitemap.xml'],
                ['key' => 'fw-pub7', 'label' => 'Daily content widget on homepage; archive + dated permalink; 1080px share card', 'where' => '/en/daily/ayah'],
                ['key' => 'fw-pub8', 'label' => 'Prayer times page + homepage widget + JSON API; island picker', 'where' => '/en/prayer-times'],
                ['key' => 'fw-pub9', 'label' => 'Research listing, permalink with PDF download, instructor profile page', 'where' => '/en/research'],
                ['key' => 'fw-pub10', 'label' => 'Careers page lists open positions', 'where' => '/en/careers'],
                ['key' => 'fw-pub11', 'label' => 'Certificate QR verification shows the certificate face to a guest', 'where' => '/verify/certificates/{ulid}'],
                ['key' => 'fw-pub12', 'label' => 'Public library: browse, item page, watermarked reader, paid item purchase gate', 'where' => '/en/library'],
                ['key' => 'fw-pub13', 'label' => 'Trilingual: /dv and /ar render RTL with Thaana/Arabic fonts', 'where' => '/dv'],
            ]],
            ['key' => 'fw-admissions', 'title' => 'Admissions & checkout', 'items' => [
                ['key' => 'fw-adm1', 'label' => 'Public registration: start → OTP SMS verify → enroll in selected courses', 'where' => '/en/register'],
                ['key' => 'fw-adm2', 'label' => 'Paid checkout: BML redirect → webhook (not return URL) activates the enrollment', 'where' => '/en/register'],
                ['key' => 'fw-adm3', 'label' => 'Free + paid cart: free courses activate immediately, paid wait for the webhook', 'where' => '/en/register'],
                ['key' => 'fw-adm4', 'label' => 'Discount code applies at checkout; wallet and gift cards usable as payment', 'where' => '/en/register'],
                ['key' => 'fw-adm5', 'label' => 'Funnel report: course_view → register_click → started → paid, with CSV', 'where' => '/admin/public-site/funnel'],
                ['key' => 'fw-adm6', 'label' => 'Leads listing (waitlist + syllabus) with CSV', 'where' => '/admin/public-site/leads'],
            ]],
            ['key' => 'fw-engine', 'title' => 'Course engine — authoring', 'items' => [
                ['key' => 'fw-eng1', 'label' => 'Catalog CRUD: categories, courses, publish state, i18n preview', 'where' => '/catalog/courses'],
                ['key' => 'fw-eng2', 'label' => 'Outline: modules, lessons, revisions; pin a revision; preview toggle', 'where' => '/catalog/courses/{id}'],
                ['key' => 'fw-eng3', 'label' => 'Blocks: text, media, and the extra block types render in the player', 'where' => '/catalog/courses/{id}'],
                ['key' => 'fw-eng4', 'label' => 'Glossary term bank; terms attached to lessons show in the player', 'where' => '/catalog/glossary'],
                ['key' => 'fw-eng5', 'label' => 'Activities: all four patterns; question bank; standards tagging', 'where' => '/catalog/courses/{id}'],
                ['key' => 'fw-eng6', 'label' => 'Offerings: create, seats, pin, price override; sessions + session attendance', 'where' => '/catalog/offerings'],
                ['key' => 'fw-eng7', 'label' => 'Certificate templates; issue to a completer; CSV of issued', 'where' => '/catalog/certificates'],
            ]],
            ['key' => 'fw-learning', 'title' => 'Learning (student/parent)', 'items' => [
                ['key' => 'fw-lrn1', 'label' => 'Player: sequential unlock, completion from required lessons + sessions', 'where' => '/learn'],
                ['key' => 'fw-lrn2', 'label' => 'Quiz attempt scores; teacher-marked activity goes to review; feedback visible', 'where' => '/learn'],
                ['key' => 'fw-lrn3', 'label' => 'Teacher review queue: pending, weakness report, revision suggestions, CSV', 'where' => '/catalog/reviews'],
                ['key' => 'fw-lrn4', 'label' => 'Completion report for staff; performance card in the parent portal', 'where' => '/catalog/reports/completions'],
                ['key' => 'fw-lrn5', 'label' => 'Scheduled session views for learners and teachers', 'where' => '/learn/schedule'],
            ]],
            ['key' => 'fw-arabic', 'title' => 'Arabic & Qur\'an components', 'items' => [
                ['key' => 'fw-ara1', 'label' => 'Arabic letters/harakas reference data drives skill-tagged activities', 'where' => '/catalog/courses/{id}'],
                ['key' => 'fw-ara2', 'label' => 'Arabic skill reports aggregate per-skill performance', 'where' => '/catalog/reports'],
                ['key' => 'fw-qur1', 'label' => 'Hifz assignment → student recitation submission → teacher review queue', 'where' => '/teach/recitations'],
                ['key' => 'fw-qur2', 'label' => 'Qur\'an milestones and session records review', 'where' => '/teach/milestones'],
                ['key' => 'fw-qur3', 'label' => 'Qur\'an translations via provider on ayah displays (licensed import pending)', 'where' => '/en/daily/ayah'],
                ['key' => 'fw-qur4', 'label' => 'Legacy Hifz Blade dashboards still function (frozen, pre-retirement)', 'where' => '/hifz'],
            ]],
            ['key' => 'fw-ai', 'title' => 'Pronunciation AI (flag off by default)', 'items' => [
                ['key' => 'fw-ai1', 'label' => 'Practice recorder stores attempts; human queue when AI is off', 'where' => '/learn/pronounce'],
                ['key' => 'fw-ai2', 'label' => 'Teacher verdicts become training samples; admin approves + exports dataset', 'where' => '/admin/pronunciation'],
                ['key' => 'fw-ai3', 'label' => 'Model shelf: register/activate/rollback, audited, one active per type', 'where' => '/admin/pronunciation'],
            ]],
            ['key' => 'fw-academics', 'title' => 'School — academics', 'items' => [
                ['key' => 'fw-aca1', 'label' => 'Academic years/terms: single active year, close-after-terms, promotion dry-run', 'where' => '/academics/years'],
                ['key' => 'fw-aca2', 'label' => 'Classes with rosters; class teacher assignment; year+name uniqueness', 'where' => '/academics/classes'],
                ['key' => 'fw-aca3', 'label' => 'Periods CRUD + rooms CRUD with CSV', 'where' => '/academics/periods'],
                ['key' => 'fw-aca4', 'label' => 'Timetable builder week grid; teacher/room/class conflict rejection', 'where' => '/academics/timetable'],
                ['key' => 'fw-aca5', 'label' => 'Room bookings; clash against timetable slots', 'where' => '/academics/rooms'],
                ['key' => 'fw-aca6', 'label' => 'Calendar days: holidays/closures; skip-days affect register generation', 'where' => '/academics/calendar'],
                ['key' => 'fw-aca7', 'label' => 'Class register: teacher lands on Today; generate, fill (number+DOB grid), submit', 'where' => '/teach/registers'],
                ['key' => 'fw-aca8', 'label' => 'Class attendance per-lesson/daily modes; SMS throttle; chronic absence list', 'where' => '/academics/attendance'],
                ['key' => 'fw-aca9', 'label' => 'Absence notes: parent submits, teacher approves → matching rows excused', 'where' => '/academics/absence-notes'],
                ['key' => 'fw-aca10', 'label' => 'Behavior records; parent visibility flag respected', 'where' => '/academics/behavior'],
                ['key' => 'fw-aca11', 'label' => 'School requests / leave with review flow', 'where' => '/academics/requests'],
                ['key' => 'fw-aca12', 'label' => 'Parent-teacher meeting slots: generate, publish, portal booking, CSV', 'where' => '/academics/meetings'],
                ['key' => 'fw-aca13', 'label' => 'Events/electives: seats, waitlist, parent confirm, second-round promotion', 'where' => '/academics/events'],
            ]],
            ['key' => 'fw-exams', 'title' => 'School — exams & grades', 'items' => [
                ['key' => 'fw-exa1', 'label' => 'Grading scales, assessment types, weight schemes summing to 100', 'where' => '/exams/settings'],
                ['key' => 'fw-exa2', 'label' => 'Exam scheduling status machine → published', 'where' => '/exams'],
                ['key' => 'fw-exa3', 'label' => 'Marks grid with student numbers; CSV', 'where' => '/exams'],
                ['key' => 'fw-exa4', 'label' => 'Term grades recompute: Term %, grade, rank in the gradebook', 'where' => '/exams/gradebook'],
                ['key' => 'fw-exa5', 'label' => 'Unified gradebook shows engine quiz/assignment items beside exams', 'where' => '/exams/gradebook'],
                ['key' => 'fw-exa6', 'label' => 'Report cards: template, queued HTML render (worker required)', 'where' => '/exams/report-cards'],
                ['key' => 'fw-exa7', 'label' => 'Awards + ID cards render as HTML documents', 'where' => '/academics/awards'],
            ]],
            ['key' => 'fw-finance', 'title' => 'School — finance', 'items' => [
                ['key' => 'fw-fin1', 'label' => 'Fee structures per year/class; invoice generation, issue, arrears', 'where' => '/finance/invoices'],
                ['key' => 'fw-fin2', 'label' => 'Payment plans and fee adjustments apply to invoices', 'where' => '/finance'],
                ['key' => 'fw-fin3', 'label' => 'Parent Fees tab: invoices listed, Pay now via BML, receipt on webhook', 'where' => '/portal/fees'],
                ['key' => 'fw-fin4', 'label' => 'Admin payments: refund to wallet/manual; refunded filter; CSV export', 'where' => '/admin/enrollments/payments'],
                ['key' => 'fw-fin5', 'label' => 'Manual payment on an enrollment activates without BML', 'where' => '/admin/enrollments/{id}'],
                ['key' => 'fw-fin6', 'label' => 'payments:reconcile closes pending payments by reference (single path)', 'where' => 'artisan payments:reconcile'],
            ]],
            ['key' => 'fw-people', 'title' => 'People & identity', 'items' => [
                ['key' => 'fw-peo1', 'label' => 'Student directory: create/edit, custom fields, status via action only', 'where' => '/people/students'],
                ['key' => 'fw-peo2', 'label' => 'Roster picker matches by identity fields, flags indistinguishable candidates', 'where' => '/academics/classes'],
                ['key' => 'fw-peo3', 'label' => 'Consent ledger on the student profile (incl. prayer/daily SMS consent)', 'where' => '/people/students/{id}'],
                ['key' => 'fw-peo4', 'label' => 'Staff profiles; teachers row backs teacher features', 'where' => '/people/staff'],
                ['key' => 'fw-peo5', 'label' => 'students:verify-unification gate green on the representative dataset', 'where' => 'artisan students:verify-unification'],
            ]],
            ['key' => 'fw-portal', 'title' => 'Portal & landings', 'items' => [
                ['key' => 'fw-por1', 'label' => 'Role landings: teacher → Today, parent/student → Home, admin → Overview', 'where' => '/dashboard'],
                ['key' => 'fw-por2', 'label' => 'Composed parent/student home: attendance, exams, invoices, courses, Hifz; CSV', 'where' => '/portal/home'],
                ['key' => 'fw-por3', 'label' => 'Staff overview: unfilled registers, fill rates, ungraded exams, plan adherence', 'where' => '/portal/overview'],
                ['key' => 'fw-por4', 'label' => 'Portal meetings booking; portal events registration', 'where' => '/portal/meetings'],
            ]],
            ['key' => 'fw-hr', 'title' => 'HR & payroll', 'items' => [
                ['key' => 'fw-hr1', 'label' => 'Staff attendance (CSV import supported); leave management', 'where' => '/hr/attendance'],
                ['key' => 'fw-hr2', 'label' => 'Contracts/compliance; recruitment feeds public careers', 'where' => '/hr'],
                ['key' => 'fw-hr3', 'label' => 'Performance/CPD records', 'where' => '/hr/performance'],
                ['key' => 'fw-hr4', 'label' => 'Payroll behind PAYROLL_ENABLED (default off, by design)', 'where' => '/hr/payroll'],
            ]],
            ['key' => 'fw-notify', 'title' => 'Notifications & SMS', 'items' => [
                ['key' => 'fw-not1', 'label' => 'Daily content subscriptions: opt-in, 15-min deliverer, STOP + token unsubscribe', 'where' => '/admin/public-site/daily-subscriptions'],
                ['key' => 'fw-not2', 'label' => 'Prayer broadcast: preview cost → confirm → queue → send via SMS contract', 'where' => '/admin/prayer-times'],
                ['key' => 'fw-not3', 'label' => 'Maker-checker on daily content: creator cannot approve own item', 'where' => '/admin/public-site/daily-content'],
                ['key' => 'fw-not4', 'label' => 'SMS binds to log outside production — no live sends from test', 'where' => 'config/services'],
            ]],
            ['key' => 'fw-platform', 'title' => 'Platform & admin', 'items' => [
                ['key' => 'fw-pla1', 'label' => 'Settings admin; trust/conversion/daily settings groups', 'where' => '/admin/settings'],
                ['key' => 'fw-pla2', 'label' => 'Users & roles (super_admin); permission-gated admin areas 403 correctly', 'where' => '/admin/users'],
                ['key' => 'fw-pla3', 'label' => 'morph-map:verify passes — no FQCNs in morph columns', 'where' => 'artisan morph-map:verify'],
                ['key' => 'fw-pla4', 'label' => 'Operator close-out checklist (this area) shared with attribution', 'where' => '/admin/operations'],
                ['key' => 'fw-pla5', 'label' => 'PWA: manifest, service worker, offline page; i18n strings aligned en/dv/ar', 'where' => '/offline'],
                ['key' => 'fw-pla6', 'label' => 'Capacitor mobile shell builds per docs/MOBILE.md (device work)', 'where' => 'docs/MOBILE.md'],
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
     * @return array{sections: mixed, checked: array<string, array{by: string|null, at: string}>, done: int, total: int}
     */
    public function execute(): array
    {
        $rows = OperatorCheck::query()->where('item_key', 'like', 'fw-%')->get();
        $names = DB::table('users')
            ->whereIn('id', $rows->pluck('checked_by')->filter()->all())
            ->pluck('name', 'id');

        $checked = [];
        foreach ($rows as $row) {
            $checked[$row->item_key] = [
                'by' => $row->checked_by !== null ? ($names[$row->checked_by] ?? null) : null,
                'at' => $row->checked_at->toDateTimeString(),
            ];
        }

        return [
            'sections' => self::definitions(),
            'checked' => $checked,
            'done' => count($checked),
            'total' => count(self::itemKeys()),
        ];
    }
}
