<?php

use App\Domains\Identity\Models\User;
use App\Support\Navigation\BuildNavigationAction;
use App\Support\Navigation\ResolveWorkspacesAction;
use App\Support\Navigation\WorkspaceMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * Workspaces (STATUS §5id, ADR-040): one per job a person holds, each with
 * its own home, bar and More menu; the shell shows one at a time and a
 * switcher for the others. The owner, 2026-09-27: "system admin:
 * everything related to website, system, shop, bookstore, library. dean,
 * supervisor, educational admin, teacher: education … their setting
 * should be seen when he changes to his specific role."
 */
const INSTITUTE_PERMISSIONS = ['bookshop.manage', 'commerce.manage', 'library.manage', 'prayer.manage', 'pronunciation.manage', 'operations.manage', 'translations.manage'];

const SCHOOL_PERMISSIONS = ['registers.manage', 'registers.fill', 'exams.manage', 'manage_attendance', 'operations.manage'];

function workspaceUser(array $roles, array $permissions = []): User
{
    // The roles' real grants, so what a role sees is what it is seeded with.
    test()->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create();
    foreach ($roles as $role) {
        $user->assignRole(Role::findOrCreate($role, 'web'));
    }
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    if ($permissions !== []) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

it('holds one workspace per job, staff first, and the account alone for a person with no role', function () {
    $resolve = fn (array $roles) => app(ResolveWorkspacesAction::class)->execute(workspaceUser($roles));

    $super = $resolve(['super_admin']);
    expect(array_column($super['list'], 'key'))->toBe(['institute'])->and($super['active'])->toBe('institute')
        ->and($super['list'][0]['href'])->toBe('/admin')->and($super['list'][0]['label'])->toBe('Institute');

    $admin = $resolve(['admin']);
    expect(array_column($admin['list'], 'key'))->toBe(['school'])->and($admin['list'][0]['href'])->toBe('/school');

    // A teacher who runs nothing else lands on their day, inside the School.
    $teacher = $resolve(['teacher']);
    expect(array_column($teacher['list'], 'key'))->toBe(['school'])->and($teacher['list'][0]['href'])->toBe('/portal/teacher');

    $teacherParent = $resolve(['teacher', 'parent']);
    expect(array_column($teacherParent['list'], 'key'))->toBe(['school', 'family'])->and($teacherParent['active'])->toBe('school');
    expect($resolve(['parent', 'teacher']))->toBe($teacherParent);

    $several = $resolve(['parent', 'student', 'vendor', 'writer']);
    expect(array_column($several['list'], 'key'))->toBe(['family', 'learn', 'vendor', 'writing'])
        ->and(array_column($several['list'], 'href'))->toBe(['/portal/home', '/portal/home', '/vendor', '/write'])
        ->and($several['active'])->toBe('family');

    expect(array_column($resolve(['super_admin', 'reviewer', 'course_creator'])['list'], 'key'))->toBe(['institute', 'writing', 'catalog'])
        ->and($resolve(['reviewer'])['list'][0]['href'])->toBe('/review')
        ->and($resolve(['bookshop_manager'])['list'][0]['href'])->toBe('/admin/bookshop');

    $nobody = $resolve([]);
    expect(array_column($nobody['list'], 'key'))->toBe([WorkspaceMap::ACCOUNT])->and($nobody['list'][0]['href'])->toBe('/dashboard');

    expect(app(ResolveWorkspacesAction::class)->execute(null))->toBe(['active' => null, 'list' => []]);
});

it('builds the bar and the More menu for the active workspace only', function () {
    $super = workspaceUser(['super_admin'], INSTITUTE_PERMISSIONS);
    $nav = app(BuildNavigationAction::class)->execute($super, 'en');
    expect($nav['workspace'])->toBe('institute')
        ->and(array_column($nav['primary'], 'label'))->toBe(['Website CMS', 'Commerce', 'Library office', 'Akuru Bookstore', 'Manage users'])
        ->and(array_column($nav['groups'], 'key'))->toBe(['panel_website', 'panel_money', 'panel_system', 'mine']);
    $hrefs = navHrefs($nav);
    expect($hrefs)->toContain('/admin/public-site/pages', '/admin/instructors', '/admin/prayer-times/islands', '/admin/commerce', '/admin/library', '/admin/bookshop', '/admin/users', '/admin/settings', '/admin/translations')
        ->not->toContain('/admin/enrollments', '/academics/years', '/hr/payroll', '/exams/schedule');

    $admin = workspaceUser(['admin'], SCHOOL_PERMISSIONS);
    $nav = app(BuildNavigationAction::class)->execute($admin, 'en');
    expect($nav['workspace'])->toBe('school')
        ->and(array_column($nav['primary'], 'label'))->toBe(['Today', 'Years', 'Students', 'Exams', 'Gradebook', 'Invoices'])
        ->and(array_column($nav['groups'], 'key'))->toBe(['panel_admissions', 'school_year', 'people', 'day_loop', 'exams_group', 'catalog_group', 'learn_group', 'finance_group', 'hr_group', 'library_group', 'mine']);
    $hrefs = navHrefs($nav);
    expect($hrefs)->toContain('/admin/enrollments', '/academics/years', '/hr/payroll', '/finance/invoices', '/announcements', '/quran-progress')
        ->not->toContain('/admin/commerce', '/admin/public-site/pages', '/admin/users', '/admin/settings');

    // The teacher's bar inside the School; the Blade screens the Blade nav used to link by hand.
    $teacher = workspaceUser(['teacher'], ['registers.fill']);
    $nav = app(BuildNavigationAction::class)->execute($teacher, 'en');
    expect(array_column($nav['primary'], 'label'))->toContain('Today', 'Teach', 'Check in')
        ->and(navHrefs($nav))->toContain('/announcements', '/substitutions/requests', '/quran-progress', '/e-learning')
        ->not->toContain('/admin/enrollments');

    // Asked for a workspace outright: a system admin who is also the dean.
    $both = workspaceUser(['super_admin', 'headmaster'], INSTITUTE_PERMISSIONS + SCHOOL_PERMISSIONS);
    $school = app(BuildNavigationAction::class)->execute($both, 'en', 'school');
    $institute = app(BuildNavigationAction::class)->execute($both, 'en', 'institute');
    expect(array_column($school['groups'], 'key'))->toContain('school_year')->not->toContain('panel_website')
        ->and(array_column($institute['groups'], 'key'))->toContain('panel_website')->not->toContain('school_year')
        ->and(array_column($institute['primary'], 'label'))->toBe(['Website CMS', 'Commerce', 'Library office', 'Akuru Bookstore', 'Manage users']);

    // A person with no role: their account, and only that.
    expect(array_column(app(BuildNavigationAction::class)->execute(workspaceUser([]), 'en')['groups'], 'key'))->toBe(['mine']);
});

function navHrefs(array $nav): array
{
    $hrefs = array_column($nav['primary'], 'href');
    foreach ($nav['groups'] as $group) {
        foreach ($group['items'] as $item) {
            $hrefs[] = $item['href'];
            foreach ($item['children'] ?? [] as $child) {
                $hrefs[] = $child['href'];
            }
        }
    }

    return $hrefs;
}

it('switches with a post, remembers the choice, and remembers a home that is opened', function () {
    $user = workspaceUser(['teacher', 'parent'], ['registers.fill']);
    makeTeacherRow()->forceFill(['user_id' => $user->id])->save();

    // Lands in the School; both workspaces are shared with every Inertia page.
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('dashboard'))->assertRedirect(route('portal.teacher'));
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('portal.teacher'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.workspace', 'school')
            ->has('auth.workspaces', 2)
            ->where('auth.workspaces.0', ['key' => 'school', 'label' => 'School', 'href' => '/portal/teacher', 'route' => 'portal.teacher'])
            ->where('auth.workspaces.1.label', 'Family')
            ->where('nav.workspace', 'school')
            ->where('nav.groups', fn ($groups) => collect($groups)->pluck('key')->contains('day_loop'))
            ->where('i18n.nav.workspaces', 'Workspaces'));

    // The switch: a post, then the family's home with the family's menus.
    $this->withoutLocalizationMiddleware()->actingAs($user)->post(route('workspace.switch', 'family'))
        ->assertRedirect(route('portal.home'))
        ->assertSessionHas('workspace', 'family');
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('portal.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.workspace', 'family')
            ->where('nav.workspace', 'family')
            ->where('nav.groups', fn ($groups) => collect($groups)->pluck('key')->all() === ['learn_group', 'mine']));
    // The choice holds on any other page.
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('portal.notifications'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.workspace', 'family'));

    // Opening the School's home makes the School active again.
    $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('school.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/WorkspaceHome')->where('workspace', 'school')->where('auth.workspace', 'school'))
        ->assertSessionHas('workspace', 'school');

    // A workspace they do not hold is not there to switch to.
    $this->withoutLocalizationMiddleware()->actingAs($user)->post(route('workspace.switch', 'institute'))->assertNotFound();
    $this->withoutLocalizationMiddleware()->actingAs($user)->post(route('workspace.switch', 'nonsense'))->assertNotFound();
});

it('sends each person to their workspace home from /dashboard, and from a home that is not theirs', function () {
    $land = fn (User $user) => $this->withoutLocalizationMiddleware()->actingAs($user)->get(route('dashboard'));

    $land(workspaceUser(['super_admin']))->assertRedirect(route('admin.index'));
    $land(workspaceUser(['admin']))->assertRedirect(route('school.index'));
    $land(workspaceUser(['headmaster']))->assertRedirect(route('school.index'));
    $land(workspaceUser(['supervisor']))->assertRedirect(route('school.index'));
    $land(workspaceUser(['bookshop_manager']))->assertRedirect(route('admin.bookshop.index'));
    $land(workspaceUser(['parent']))->assertRedirect(route('portal.home'));
    $land(workspaceUser(['vendor']))->assertRedirect(route('vendor.index'));

    // The wrong home sends a person to their own, not to a 403.
    $this->withoutLocalizationMiddleware()->actingAs(workspaceUser(['admin']))->get(route('admin.index'))->assertRedirect(route('dashboard'));
    $this->withoutLocalizationMiddleware()->actingAs(workspaceUser(['super_admin']))->get(route('school.index'))->assertRedirect(route('dashboard'));
    $this->withoutLocalizationMiddleware()->actingAs(workspaceUser(['bookshop_manager']))->get(route('admin.index'))->assertRedirect(route('dashboard'));
    $this->withoutLocalizationMiddleware()->actingAs(workspaceUser(['parent']))->get(route('school.index'))->assertRedirect(route('dashboard'));
    app('auth')->forgetGuards();
    $this->withoutLocalizationMiddleware()->get(route('school.index'))->assertRedirect();
});

it('composes the Institute and the School homes from the workspace’s own menu', function () {
    $super = workspaceUser(['super_admin'], INSTITUTE_PERMISSIONS);
    $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/WorkspaceHome')
            ->where('workspace', 'institute')
            ->where('t.institute_title', 'Institute')
            ->has('parts', 3)
            ->where('parts.0.key', 'panel_website')->where('parts.0.label', 'Website & content')->has('parts.0.sections', 4)
            ->where('parts.0.sections.0.key', 'website_cms')->has('parts.0.sections.0.children', 8)->where('parts.0.sections.0.hard', false)
            ->where('parts.0.sections.1.key', 'admin_instructors')
            ->where('parts.0.sections.2.key', 'prayer_times')->has('parts.0.sections.2.children', 4)
            ->where('parts.1.key', 'panel_money')->has('parts.1.sections', 3)
            ->where('parts.2.key', 'panel_system')->has('parts.2.sections', 5)->where('parts.2.sections.0.key', 'manage_users')
            ->has('today.tiles', 2)->where('today.tiles.0.key', 'new_accounts')->where('today.tiles.1.key', 'paid_today')
            ->where('today.more.href', '/dashboard/numbers'));

    $admin = workspaceUser(['admin'], SCHOOL_PERMISSIONS);
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('school.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/WorkspaceHome')
            ->where('workspace', 'school')
            ->where('t.school_title', 'School office')
            ->has('parts', 3)
            ->where('parts.0.key', 'panel_admissions')->has('parts.0.sections', 1)
            ->where('parts.0.sections.0.key', 'admin_enrolments')->where('parts.0.sections.0.href', '/admin/enrollments')->where('parts.0.sections.0.hard', false)->has('parts.0.sections.0.children', 1)
            ->where('parts.1.key', 'school_academics')->where('parts.1.label', 'Academics')
            ->where('parts.1.sections', fn ($sections) => collect($sections)->pluck('key')->all() === ['school_year', 'day_loop', 'exams_group', 'catalog_group', 'learn_group'])
            ->where('parts.1.sections.0.href', '/academics/years')->where('parts.1.sections.0.hard', false)->has('parts.1.sections.0.children', 11)
            ->where('parts.1.sections.0.description', 'Years, terms, rooms, periods, the timetable, calendar, events and clubs.')
            ->where('parts.2.key', 'school_office')->where('parts.2.label', 'Office')
            ->where('parts.2.sections', fn ($sections) => collect($sections)->pluck('key')->all() === ['people', 'finance_group', 'hr_group', 'library_group'])
            ->has('today.tiles', 5)->where('today.tiles.0.key', 'pending_payment')->where('today.tiles.3.key', 'unfilled_registers')
            ->where('today.more.href', '/portal/overview'));

    // A supervisor runs no admissions (ADR-040 slice 2), so the School office
    // opens on the academics for them.
    $supervisor = workspaceUser(['supervisor']);
    $this->withoutLocalizationMiddleware()->actingAs($supervisor)->get(route('school.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('workspace', 'school')
            ->where('today.tiles', fn ($tiles) => collect($tiles)->pluck('key')->contains('students_on_roll'))
            ->where('parts.0.key', 'school_academics'));
    $this->withoutLocalizationMiddleware()->actingAs(workspaceUser(['headmaster']))->get(route('school.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('parts.0.key', 'panel_admissions'));

    // A teacher opens the School office too, shaped by what they may open.
    $teacher = workspaceUser(['teacher'], ['registers.fill']);
    $this->withoutLocalizationMiddleware()->actingAs($teacher)->get(route('school.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('workspace', 'school')
            ->where('parts', fn ($parts) => ! collect($parts)->pluck('key')->contains('panel_admissions'))
            ->has('today.tiles', 0));

    // In Dhivehi.
    app()->setLocale('dv');
    $this->withoutLocalizationMiddleware()->actingAs($admin)->get(route('school.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('t.school_title', 'ސްކޫލް އޮފީސް')->where('parts.0.label', 'އެޑްމިޝަން')->where('parts.1.label', 'އެކަޑެމިކްސް'));
    app()->setLocale('en');
});

it('renders the Blade shell from the same map, with the switcher for a person who holds several workspaces', function () {
    $adminVendor = workspaceUser(['admin', 'vendor'], SCHOOL_PERMISSIONS);
    // The Quran progress list is the School's Blade-shell fixture: the enrolment lists are Inertia since C9 slice 4.
    $html = $this->withoutLocalizationMiddleware()->actingAs($adminVendor)->get(route('quran-progress.index'))->assertOk()->getContent();
    // The School's groups, in the More menu and the phone menu.
    expect(substr_count($html, 'data-nav-section="school_year"'))->toBe(2)
        ->and(substr_count($html, 'data-nav-section="panel_admissions"'))->toBe(2)
        ->and($html)->not->toContain('data-nav-section="panel_website"')
        ->and($html)->toContain('/admin/enrollments')->toContain('/academics/years')->not->toContain('/admin/commerce')
        // The switcher: the header pill, the user menu and the phone menu.
        ->and(substr_count($html, 'data-testid="workspace-vendor"'))->toBe(3)
        ->and(substr_count($html, 'data-testid="workspace-school"'))->toBe(3)
        ->and($html)->toContain('data-testid="workspace-switcher"')->toContain('Workspaces')
        ->and(substr_count($html, 'data-testid="workspace-home"'))->toBe(2);

    // One workspace: no switcher anywhere.
    $lone = $this->withoutLocalizationMiddleware()->actingAs(workspaceUser(['admin']))->get(route('quran-progress.index'))->assertOk()->getContent();
    expect($lone)->not->toContain('Workspaces')->not->toContain('data-testid="workspace-switcher"');

    // The system admin on a Blade CMS screen sees the Institute, not the School.
    $super = workspaceUser(['super_admin'], INSTITUTE_PERMISSIONS);
    // The courses CMS: the pages CMS is Inertia since C9 slice 10.
    $cms = $this->withoutLocalizationMiddleware()->actingAs($super)->get(route('admin.courses.index'))->assertOk()->getContent();
    expect($cms)->toContain('data-nav-section="panel_website"')->toContain('/admin/commerce')
        ->not->toContain('data-nav-section="school_year"')->not->toContain('/admin/enrollments');
});
