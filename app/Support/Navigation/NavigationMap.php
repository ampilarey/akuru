<?php

namespace App\Support\Navigation;

/**
 * Where every Inertia screen lives in the shell's navigation.
 *
 * The map is grouped **by role and by frequency** (docs/APPSHELL_NAV_IA.md):
 * the handful of destinations a role uses in its first week of term sit in
 * the always-visible primary bar; everything else is one labelled group in
 * the *More* menu. Nothing here decides who may open a page — every route
 * still enforces its own gate. `BuildNavigationAction` reads that gate off
 * the route and hides links the person could only be refused; the `roles`
 * hint below is for the pages that are `auth`-only (the portal, learning,
 * teaching), where the route cannot say which kind of person the page is for.
 *
 * Labels are keys in `lang/nav.php`; the English text beside each is the
 * fallback and what the tests read.
 */
final class NavigationMap
{
    public const ADMINS = ['super_admin', 'admin', 'headmaster', 'supervisor'];

    public const STAFF = ['super_admin', 'admin', 'headmaster', 'supervisor', 'teacher'];

    public const FAMILY = ['parent', 'student'];

    /**
     * The primary bar per role. A person with several roles gets the union,
     * in this order, without repeats.
     *
     * @return array<string, list<array{key: string, href: string, can?: list<string>}>>
     */
    public static function primary(): array
    {
        $fills = ['registers.fill', 'registers.manage'];

        return [
            'admins' => [
                ['key' => 'today', 'href' => '/academics/registers/today', 'can' => $fills],
                ['key' => 'years', 'href' => '/academics/years'],
                ['key' => 'students', 'href' => '/people/students'],
                ['key' => 'exams', 'href' => '/exams/schedule'],
                ['key' => 'gradebook', 'href' => '/exams/gradebook', 'can' => ['exams.manage', 'exams.enter-any']],
                ['key' => 'invoices', 'href' => '/finance/invoices'],
            ],
            'teacher' => [
                ['key' => 'my_day', 'href' => '/portal/teacher', 'can' => $fills],
                ['key' => 'today', 'href' => '/academics/registers/today', 'can' => $fills],
                ['key' => 'registers', 'href' => '/academics/registers', 'can' => ['registers.manage']],
                ['key' => 'review_notes', 'href' => '/academics/absence-notes', 'can' => ['manage_attendance']],
                ['key' => 'teach', 'href' => '/teach/schedule'],
                ['key' => 'check_in', 'href' => '/portal/staff-check-in'],
            ],
            'parent' => [
                ['key' => 'children', 'href' => '/portal/children'],
                ['key' => 'fees', 'href' => '/portal/invoices'],
                ['key' => 'results', 'href' => '/portal/exams'],
                ['key' => 'absence_notes', 'href' => '/portal/absence-notes'],
                ['key' => 'school_calendar', 'href' => '/portal/holidays'],
            ],
            'student' => [
                ['key' => 'learn', 'href' => '/learn'],
                ['key' => 'schedule', 'href' => '/learn/schedule'],
                ['key' => 'homework', 'href' => '/portal/homework'],
                ['key' => 'my_attendance', 'href' => '/portal/attendance'],
                ['key' => 'results', 'href' => '/portal/exams'],
            ],
            'course_creator' => [
                ['key' => 'catalog', 'href' => '/catalog/courses'],
                ['key' => 'offerings', 'href' => '/catalog/offerings'],
                ['key' => 'questions', 'href' => '/catalog/questions'],
            ],
            'writer' => [
                ['key' => 'write', 'href' => '/write'],
            ],
            'reviewer' => [
                ['key' => 'review', 'href' => '/review'],
            ],
            // BOOKSHOP_PLAN B1a: a vendor member's own shop.
            'vendor' => [
                ['key' => 'vendor_portal', 'href' => '/vendor'],
            ],
        ];
    }

    /**
     * Which primary bars a role draws from.
     *
     * @return list<string>
     */
    public static function barsFor(string $role): array
    {
        return match ($role) {
            'super_admin', 'admin', 'headmaster', 'supervisor' => ['admins'],
            default => [$role],
        };
    }

    /**
     * The *More* menu. Items carry `roles` only where the route is `auth`-only
     * and cannot say who the page is for.
     *
     * @return list<array{key: string, items: list<array{key: string, href: string, roles?: ?list<string>, can?: list<string>}>}>
     */
    public static function groups(): array
    {
        $staff = self::STAFF;
        $family = self::FAMILY;
        $everyone = null;
        // Pages whose gate is in the controller rather than on the route: the
        // hint mirrors it, any one of the abilities admits.
        $fills = ['registers.fill', 'registers.manage'];

        return [
            ['key' => 'school_year', 'items' => [
                ['key' => 'years', 'href' => '/academics/years'],
                ['key' => 'rooms', 'href' => '/academics/rooms'],
                ['key' => 'periods', 'href' => '/academics/periods'],
                ['key' => 'timetable', 'href' => '/academics/timetable'],
                ['key' => 'bookings', 'href' => '/academics/bookings'],
                ['key' => 'meetings', 'href' => '/academics/meetings'],
                ['key' => 'calendar', 'href' => '/academics/calendar'],
                ['key' => 'events', 'href' => '/academics/events'],
                ['key' => 'clubs', 'href' => '/academics/clubs'],
                ['key' => 'attendance_policy', 'href' => '/academics/attendance-policy'],
                ['key' => 'absence_reasons', 'href' => '/academics/absence-types'],
            ]],
            ['key' => 'people', 'items' => [
                ['key' => 'students', 'href' => '/people/students'],
                ['key' => 'staff', 'href' => '/people/staff'],
                ['key' => 'custom_fields', 'href' => '/people/custom-fields'],
                ['key' => 'sensitive_info', 'href' => '/people/sensitive'],
            ]],
            ['key' => 'day_loop', 'items' => [
                ['key' => 'my_day', 'href' => '/portal/teacher', 'roles' => $staff, 'can' => $fills],
                ['key' => 'today', 'href' => '/academics/registers/today', 'can' => $fills],
                ['key' => 'registers', 'href' => '/academics/registers', 'can' => ['registers.manage']],
                ['key' => 'plans', 'href' => '/academics/plans', 'roles' => $staff, 'can' => $fills],
                ['key' => 'materials', 'href' => '/academics/materials', 'roles' => $staff, 'can' => $fills],
                // The whole school's report: gated like the controller, on the two
                // abilities every staff role holds and no family role does.
                ['key' => 'attendance', 'href' => '/academics/attendance', 'roles' => $staff, 'can' => ['manage_attendance', 'registers.manage']],
                ['key' => 'absences', 'href' => '/academics/attendance/absences', 'roles' => $staff, 'can' => ['mark_attendance', 'manage_attendance']],
                ['key' => 'review_notes', 'href' => '/academics/absence-notes', 'can' => ['manage_attendance']],
                ['key' => 'behavior', 'href' => '/academics/behavior', 'roles' => $staff, 'can' => ['behavior.record', 'behavior.manage']],
                ['key' => 'pickup', 'href' => '/academics/pickup'],
                ['key' => 'gate', 'href' => '/academics/gate'],
                ['key' => 'gate_cards', 'href' => '/academics/gate/cards'],
                ['key' => 'work_showcase', 'href' => '/academics/work'],
                ['key' => 'lost_and_found', 'href' => '/academics/found-items'],
            ]],
            ['key' => 'exams_group', 'items' => [
                ['key' => 'exams', 'href' => '/exams/schedule'],
                ['key' => 'weights', 'href' => '/exams/weights'],
                ['key' => 'gradebook', 'href' => '/exams/gradebook', 'can' => ['exams.manage', 'exams.enter-any']],
                ['key' => 'scales', 'href' => '/exams/scales'],
                ['key' => 'exam_types', 'href' => '/exams/types'],
                ['key' => 'competencies', 'href' => '/exams/competencies'],
                ['key' => 'standards', 'href' => '/exams/standards'],
                ['key' => 'report_templates', 'href' => '/exams/report-templates'],
                ['key' => 'report_cards', 'href' => '/exams/report-cards'],
                ['key' => 'awards', 'href' => '/exams/awards'],
            ]],
            ['key' => 'catalog_group', 'items' => [
                ['key' => 'catalog', 'href' => '/catalog/courses'],
                ['key' => 'offerings', 'href' => '/catalog/offerings'],
                ['key' => 'questions', 'href' => '/catalog/questions'],
                ['key' => 'reviews', 'href' => '/catalog/reviews'],
                ['key' => 'arabic', 'href' => '/catalog/arabic'],
                ['key' => 'arabic_report', 'href' => '/catalog/arabic/reports'],
                ['key' => 'quran', 'href' => '/catalog/quran'],
                ['key' => 'subjects', 'href' => '/catalog/subjects'],
                ['key' => 'glossary', 'href' => '/catalog/glossary'],
                ['key' => 'audiences', 'href' => '/catalog/audiences'],
                ['key' => 'levels', 'href' => '/catalog/levels'],
                ['key' => 'certificates', 'href' => '/catalog/certificates'],
                ['key' => 'reports', 'href' => '/catalog/reports'],
                ['key' => 'completions', 'href' => '/catalog/reports/completions'],
            ]],
            ['key' => 'learn_group', 'items' => [
                ['key' => 'learn', 'href' => '/learn', 'roles' => $everyone],
                ['key' => 'schedule', 'href' => '/learn/schedule', 'roles' => $everyone],
                ['key' => 'teach', 'href' => '/teach/schedule', 'roles' => $staff],
                ['key' => 'my_meetings', 'href' => '/teach/meetings', 'roles' => $staff],
                ['key' => 'recitations', 'href' => '/teach/recitations', 'roles' => $staff],
                ['key' => 'pronunciation', 'href' => '/teach/pronunciation', 'roles' => ['super_admin', 'admin', 'supervisor', 'teacher']],
                ['key' => 'children_learning', 'href' => '/portal/learning', 'roles' => ['parent']],
                ['key' => 'performance', 'href' => '/portal/performance', 'roles' => $family],
                ['key' => 'write', 'href' => '/write', 'roles' => ['writer']],
                ['key' => 'review', 'href' => '/review', 'roles' => ['reviewer']],
                ['key' => 'vendor_portal', 'href' => '/vendor', 'roles' => ['vendor']],
            ]],
            ['key' => 'finance_group', 'items' => [
                ['key' => 'fee_items', 'href' => '/finance/fee-items'],
                ['key' => 'fee_structures', 'href' => '/finance/fee-structures'],
                ['key' => 'invoices', 'href' => '/finance/invoices'],
                ['key' => 'arrears', 'href' => '/finance/arrears'],
                ['key' => 'payment_plans', 'href' => '/finance/payment-plans'],
                ['key' => 'adjustments', 'href' => '/finance/adjustments'],
                ['key' => 'manual_receipt', 'href' => '/finance/receipts/manual'],
                ['key' => 'collections', 'href' => '/finance/collections'],
                ['key' => 'reconciliation', 'href' => '/finance/reconciliation'],
                ['key' => 'finance_settings', 'href' => '/finance/settings'],
            ]],
            ['key' => 'hr_group', 'items' => [
                ['key' => 'staff_attendance', 'href' => '/hr/attendance'],
                ['key' => 'staff_reports', 'href' => '/hr/attendance/reports'],
                ['key' => 'leave_types', 'href' => '/hr/leave-types'],
                ['key' => 'leave_balances', 'href' => '/hr/leave-balances'],
                ['key' => 'contracts', 'href' => '/hr/contracts'],
                ['key' => 'compliance', 'href' => '/hr/compliance'],
                ['key' => 'jobs', 'href' => '/hr/postings'],
                ['key' => 'applications', 'href' => '/hr/applications'],
                ['key' => 'onboarding', 'href' => '/hr/onboarding'],
                ['key' => 'appraisals', 'href' => '/hr/appraisals'],
                ['key' => 'observations', 'href' => '/hr/observations'],
                ['key' => 'cpd', 'href' => '/hr/cpd'],
                ['key' => 'payroll', 'href' => '/hr/payroll'],
                ['key' => 'hr_settings', 'href' => '/hr/settings'],
            ]],
            ['key' => 'library_group', 'items' => [
                ['key' => 'circulation', 'href' => '/circulation'],
                ['key' => 'borrower_cards', 'href' => '/circulation/cards'],
            ]],
            ['key' => 'mine', 'items' => [
                ['key' => 'home', 'href' => '/portal/home', 'roles' => $everyone],
                // The staff overview: admits whoever runs registers or exams.
                ['key' => 'overview', 'href' => '/portal/overview', 'roles' => $everyone, 'can' => ['registers.manage', 'exams.manage']],
                ['key' => 'messages', 'href' => '/portal/messages', 'roles' => $everyone],
                ['key' => 'notices', 'href' => '/portal/announcements', 'roles' => $everyone],
                ['key' => 'forms', 'href' => '/portal/forms', 'roles' => $everyone],
                // The Akuru Digital Library (L-track): the shelf, what I have and
                // can continue, and the wallet that pays for it. Until
                // 2026-09-25 none of the three had a link anywhere a signed-in
                // person could see; they were reachable by typing the address.
                ['key' => 'library', 'href' => '/library', 'roles' => $everyone],
                ['key' => 'my_library', 'href' => '/my-library', 'roles' => $everyone],
                ['key' => 'my_wallet', 'href' => '/my-wallet', 'roles' => $everyone],
                // BOOKSHOP_PLAN B1b: the public bookshop.
                ['key' => 'shop', 'href' => '/shop', 'roles' => $everyone],
                // One screen for a family's requests and a staff member's leave; the
                // controller admits whoever may submit or review.
                ['key' => 'requests', 'href' => '/academics/requests', 'roles' => $everyone, 'can' => ['requests.submit', 'requests.review']],
                ['key' => 'children', 'href' => '/portal/children', 'roles' => ['parent']],
                ['key' => 'homework', 'href' => '/portal/homework', 'roles' => $family],
                ['key' => 'my_attendance', 'href' => '/portal/attendance', 'roles' => $family],
                ['key' => 'absence_notes', 'href' => '/portal/absence-notes', 'roles' => $family],
                ['key' => 'my_behavior', 'href' => '/portal/behavior', 'roles' => $family],
                ['key' => 'results', 'href' => '/portal/exams', 'roles' => $family],
                ['key' => 'my_report_cards', 'href' => '/portal/report-cards', 'roles' => $family],
                ['key' => 'my_awards', 'href' => '/portal/awards', 'roles' => $family],
                ['key' => 'fees', 'href' => '/portal/invoices', 'roles' => $family],
                ['key' => 'event_signup', 'href' => '/portal/events', 'roles' => $family],
                ['key' => 'school_calendar', 'href' => '/portal/holidays', 'roles' => $family],
                ['key' => 'lost_property', 'href' => '/portal/found-items', 'roles' => $family],
                ['key' => 'library_books', 'href' => '/portal/loans', 'roles' => $family],
                ['key' => 'collecting_my_child', 'href' => '/portal/pickup', 'roles' => ['parent']],
                ['key' => 'arrivals', 'href' => '/portal/movements', 'roles' => ['parent']],
                ['key' => 'my_childs_work', 'href' => '/portal/work', 'roles' => ['parent']],
                ['key' => 'check_in', 'href' => '/portal/staff-check-in', 'roles' => $staff],
                ['key' => 'my_leave', 'href' => '/portal/leave', 'roles' => $staff],
                ['key' => 'my_performance', 'href' => '/portal/appraisals', 'roles' => $staff],
                ['key' => 'payslips', 'href' => '/portal/payslips', 'roles' => $staff],
            ]],
            ['key' => 'admin_group', 'items' => [
                ['key' => 'ops_checklist', 'href' => '/admin/operations'],
                ['key' => 'bookshop', 'href' => '/admin/bookshop', 'can' => ['bookshop.manage']],
                ['key' => 'feature_walkthrough', 'href' => '/admin/operations/features'],
                ['key' => 'translations', 'href' => '/admin/translations'],
            ]],
        ];
    }

    /**
     * Every href the map names, once — for the test that checks each one is
     * a real GET route, so the menu can never carry a dead link.
     *
     * @return list<string>
     */
    public static function hrefs(): array
    {
        $hrefs = [];
        foreach (self::primary() as $items) {
            foreach ($items as $item) {
                $hrefs[] = $item['href'];
            }
        }
        foreach (self::groups() as $group) {
            foreach ($group['items'] as $item) {
                $hrefs[] = $item['href'];
            }
        }

        return array_values(array_unique($hrefs));
    }
}
