<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The workspace homes (`/admin` for the Institute, `/school` for the School;
 * docs/ADMIN_PANEL.md §1): what a person may open and nothing else, the
 * day's numbers on top, and the full dashboards a link away. Who lands
 * where and what each home is made of is `WorkspacesTest`; this is the gates.
 */
const HUB_PERMISSIONS = ['bookshop.manage', 'commerce.manage', 'library.manage', 'prayer.manage', 'pronunciation.manage', 'operations.manage', 'translations.manage'];

function hubUser(string $role, array $permissions = []): User
{
    // Every school role carries its set by migration (STATUS §5ij); these
    // tests are about the gates, so the role is a bare key and the actor
    // holds exactly `$permissions`.
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web')->syncPermissions([]));
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

it('shows the system admin every Institute section, each inner screen a real route inside its section, and sends others to their own home', function () {
    // The system admin passes every gate by design (Gate::before), so the
    // Institute home is the whole panel less Admissions, which is the School's.
    $super = hubUser('super_admin', HUB_PERMISSIONS);
    test()->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('parts', fn ($parts) => collect($parts)->pluck('key')->all() === ['panel_website', 'panel_money', 'panel_system'])
            ->where('parts.0.sections', fn ($sections) => collect($sections)->pluck('key')->all() === ['website_cms', 'admin_instructors', 'prayer_times', 'pronunciation_office'])
            ->where('parts.1.sections', fn ($sections) => collect($sections)->pluck('key')->all() === ['commerce', 'library_office', 'bookshop', 'akuru_fulfilment'])
            ->where('parts.2.sections', fn ($sections) => collect($sections)->pluck('key')->all() === ['manage_users', 'system_settings', 'ops_checklist', 'feature_walkthrough', 'translations']));

    // Every inner screen is a real route inside its section.
    $routes = Route::getRoutes()->getRoutesByMethod()['GET'];
    foreach (\App\Support\Navigation\NavigationMap::adminPanel() as $group) {
        foreach ($group['items'] as $item) {
            foreach ($item['children'] ?? [] as $child) {
                expect(isset($routes[ltrim($child['href'], '/')]))->toBeTrue("no GET route for {$child['href']}");
                expect(str_starts_with($child['href'], $item['href'] === '/admin/public-site/pages' ? '/admin/public-site' : dirname($item['href'])))->toBeTrue("{$child['href']} is not inside {$item['href']}");
            }
        }
    }

    // The gates do their work for everyone else: a dean without the registers
    // ability gets the admissions numbers only (the `admin` role holds
    // `registers.manage` by its set, so it cannot be the example here).
    test()->withoutLocalizationMiddleware()->actingAs(hubUser('headmaster', ['operations.manage']))->get(route('school.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('today.tiles', 3)->where('today.more', null));
    // The educational admin, whose set holds `registers.manage`, gets the registers numbers too.
    test()->withoutLocalizationMiddleware()->actingAs(hubUser('admin', ['registers.manage']))->get(route('school.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('today.tiles', 5)->where('today.more.href', '/portal/overview'));

    // A Bookstore manager's home is the Bookstore office, not the Institute.
    test()->withoutLocalizationMiddleware()->actingAs(hubUser('bookshop_manager', ['bookshop.manage']))->get(route('admin.index'))->assertRedirect(route('dashboard'));
});

it('puts today’s numbers on each home and keeps the full dashboards a link away, each still linking home', function () {
    $course = \App\Domains\Courses\Models\Course::query()->create([
        'course_category_id' => \Illuminate\Support\Facades\DB::table('course_categories')->insertGetId(['name' => 'Hub category', 'slug' => 'hub-'.\Illuminate\Support\Str::random(6), 'order' => 0, 'created_at' => now(), 'updated_at' => now()]),
        'title' => 'Hub course', 'slug' => 'hub-course', 'short_desc' => 'Short.', 'body' => 'Body.', 'cover_image' => '', 'workflow_status' => 'published', 'course_type' => 'general', 'status' => 'open',
    ]);
    foreach (['pending', 'pending', 'active'] as $status) {
        \App\Domains\Courses\Models\CourseEnrollment::query()->create(['course_id' => $course->id, 'unified_student_id' => makeStudent()->id, 'status' => $status, 'payment_status' => 'pending', 'enrollment_type' => 'self_learning', 'progress_percentage' => 0]);
    }
    Role::findOrCreate('supervisor', 'web');

    // The School's office: the admissions numbers from seeded enrolments.
    $admin = hubUser('admin', ['operations.manage', 'registers.manage']);
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('school.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('t.today_title', 'Today')
            ->has('today.tiles', 5)
            ->where('today.tiles.0.key', 'pending_payment')->where('today.tiles.0.value', '2')->where('today.tiles.0.href', '/admin/enrollments')->where('today.tiles.0.hard', false)
            ->where('today.tiles.1.key', 'enrolled_today')->where('today.tiles.1.value', '3')
            ->where('today.tiles.2.key', 'paid_today')->where('today.tiles.2.value', '0.00')
            ->where('today.tiles.3.key', 'unfilled_registers')->where('today.tiles.3.hard', false)
            ->where('today.more.href', '/portal/overview')->where('today.more.label', 'Staff overview'));

    // The Institute: the accounts and the money, and the system admin's full dashboard.
    $super = hubUser('super_admin', HUB_PERMISSIONS);
    test()->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('today.tiles', 2)
            ->where('today.tiles.0.key', 'new_accounts')->where('today.tiles.0.href', '/admin/users')
            ->where('today.more.href', '/dashboard/numbers')->where('today.more.label', 'Full dashboard'));

    // A supervisor: the roll and the staff, and their full dashboard.
    $supervisor = hubUser('supervisor');
    test()->withoutLocalizationMiddleware()->actingAs($supervisor)->get(route('school.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('today.tiles', 2)->where('today.tiles.0.key', 'students_on_roll')->where('today.more.href', '/dashboard/supervisor')->where('today.more.hard', false));

    // The full dashboards still say what they are and link home; the staff
    // overview offers the door to whoever may open the School, not to nobody.
    // Both dashboards are Inertia since C9 slice 13; the way home is a prop.
    test()->withoutLocalizationMiddleware()->actingAs($super)->get(route('dashboard.numbers'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/NumbersDashboard')->where('home', route('admin.index')));
    test()->withoutLocalizationMiddleware()->actingAs($supervisor)->get(route('dashboard.supervisor'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/SupervisorDashboard')->where('home', route('school.index')));
    test()->withoutLocalizationMiddleware()->actingAs($admin)->get(route('portal.overview'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/StaffOverview')
            ->where('auth.workspaces.0.href', '/school')
            ->where('i18n.nav.dashboard_hint', trans('nav.dashboard_hint')));

    expect(trans('admin.today_pending_payment', [], 'dv'))->toBe('ފައިސާ ނުދައްކާ')->and(trans('admin.today_title', [], 'ar'))->toBe('اليوم');
});
