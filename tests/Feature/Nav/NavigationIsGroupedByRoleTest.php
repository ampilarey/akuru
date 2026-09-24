<?php

use App\Domains\Identity\Models\User;
use App\Support\Navigation\BuildNavigationAction;
use App\Support\Navigation\NavigationMap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The shell's navigation is built per person (docs/APPSHELL_NAV_IA.md): a
 * short primary bar for their roles, the rest in labelled groups, and no link
 * they could only be refused. Visibility is read off each route's own guard,
 * so these tests open every link they are shown and expect not to be turned
 * away — the failure that turned the flat strip into a wall of 403s.
 */
function signedInAs(string $role): User
{
    // The roles' real grants: several pages gate in the controller on an
    // ability, and the menu mirrors those, so a bare role sees less than the
    // seeded one does.
    test()->seed(\Database\Seeders\RoleSeeder::class);
    Role::findOrCreate($role, 'web');
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function navFor(User $user): array
{
    return app(BuildNavigationAction::class)->execute($user, 'en');
}

function hrefsIn(array $nav): array
{
    $hrefs = array_column($nav['primary'], 'href');
    foreach ($nav['groups'] as $group) {
        $hrefs = [...$hrefs, ...array_column($group['items'], 'href')];
    }

    return $hrefs;
}

it('names only real GET routes, so the menu cannot carry a dead link', function () {
    $routes = Route::getRoutes()->getRoutesByMethod()['GET'];

    foreach (NavigationMap::hrefs() as $href) {
        expect(isset($routes[ltrim($href, '/')]))->toBeTrue("no GET route for {$href}");
    }
});

it('gives the office Today, Years and Exams in the bar, and the rest in groups', function () {
    $nav = navFor(signedInAs('admin'));

    expect(array_column($nav['primary'], 'label'))->toBe(['Today', 'Years', 'Students', 'Exams', 'Gradebook', 'Invoices'])
        ->and(array_column($nav['groups'], 'label'))->toContain('School year', 'People', 'Exams', 'Finance', 'HR')
        ->and(hrefsIn($nav))->toContain('/hr/payroll', '/finance/settings', '/academics/registers/today')
        // Portal pages for families are not the office's; staff self-service is.
        ->and(hrefsIn($nav))->not->toContain('/portal/children', '/portal/invoices')
        ->and(hrefsIn($nav))->toContain('/portal/staff-check-in', '/portal/payslips');
});

it('gives a teacher Today first, and none of the office-only screens', function () {
    $nav = navFor(signedInAs('teacher'));
    $hrefs = hrefsIn($nav);

    expect(array_column($nav['primary'], 'label'))->toContain('Today', 'Teach', 'Check in')
        ->and($hrefs)->toContain('/academics/registers/today', '/portal/teacher')
        ->and($hrefs)->not->toContain('/hr/payroll', '/finance/invoices', '/academics/years', '/people/students');
});

it('gives a parent their children, fees and results, and nothing of the office', function () {
    $nav = navFor(signedInAs('parent'));
    $hrefs = hrefsIn($nav);

    expect(array_column($nav['primary'], 'label'))->toBe(['Children', 'Fees', 'Results', 'Absence notes', 'School calendar'])
        ->and($hrefs)->toContain('/portal/messages', '/portal/homework', '/portal/pickup')
        ->and($hrefs)->not->toContain('/hr/payroll', '/academics/registers', '/portal/staff-check-in', '/teach/schedule');
});

it('gives a student Learn first and no family-only screens', function () {
    $nav = navFor(signedInAs('student'));
    $hrefs = hrefsIn($nav);

    expect($nav['primary'][0]['href'])->toBe('/learn')
        ->and($hrefs)->toContain('/portal/homework', '/portal/exams')
        ->and($hrefs)->not->toContain('/portal/children', '/portal/pickup', '/academics/registers/today');
});

it('lists each screen once and gives a guest nothing', function () {
    $hrefs = hrefsIn(navFor(signedInAs('super_admin')));
    $primaryAndGroups = count($hrefs);
    // A screen may sit in the bar and in its group; never twice in the groups.
    $groupHrefs = [];
    foreach (navFor(signedInAs('super_admin'))['groups'] as $group) {
        $groupHrefs = [...$groupHrefs, ...array_column($group['items'], 'href')];
    }

    expect($groupHrefs)->toBe(array_values(array_unique($groupHrefs)))
        ->and($primaryAndGroups)->toBeGreaterThan(60)
        ->and(app(BuildNavigationAction::class)->execute(null, 'en'))->toBe(['primary' => [], 'groups' => []]);
});

it('shows a teacher and a parent only screens that let them in', function (string $role) {
    // A teacher who is a teacher on the roster: today's registers gate on a
    // linked teacher profile as well as on the ability.
    $user = signedInAs($role);
    makeYear();
    if ($role === 'teacher') {
        makeTeacherRow()->forceFill(['user_id' => $user->id])->save();
    }

    $refused = [];
    foreach (hrefsIn(navFor($user)) as $href) {
        $status = $this->withoutLocalizationMiddleware()->actingAs($user)->get($href)->status();
        if (in_array($status, [403, 404], true)) {
            $refused[] = "{$href} → {$status}";
        }
    }

    expect($refused)->toBe([], "{$role} is shown links that refuse them: ".implode(', ', $refused));
})->with(['teacher', 'parent']);

it('shares the navigation with every Inertia page, labelled in the request language', function () {
    $admin = signedInAs('admin');
    makeYear();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.years.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nav.primary.0.label', 'Today')
            ->where('nav.primary.0.href', '/academics/registers/today')
            ->has('nav.groups')
            ->where('i18n.nav.more', 'More'));

    app()->setLocale('ar');
    expect(navFor($admin)['primary'][0]['label'])->toBe('اليوم');
});
