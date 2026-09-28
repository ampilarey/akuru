<?php

use App\Domains\Identity\Models\User;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Navigation\BuildNavigationAction;
use App\Support\Navigation\NavigationMap;
use App\Support\Navigation\ResolveWorkspacesAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * A workspace's menu is only its own (docs/SIGN_IN_PLAN.md, ID1). The owner,
 * 2026-09-28, with EduPage's drawer beside it: "Logged in with a vendor
 * account but I see educational items also." Until ID1 every workspace held
 * the `mine` group — Home (the family portal), Messages, Notices, Forms —
 * and the family's, the pupil's and the writer's held `learn_group`, which
 * put a person's own learning into their children's menu.
 */
function menuPerson(array $roles): User
{
    test()->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole($roles);

    return $user;
}

function menuHrefs(User $user, ?string $workspace = null): array
{
    $nav = app(BuildNavigationAction::class)->execute($user, 'en', $workspace);
    $hrefs = array_column($nav['primary'], 'href');
    foreach ($nav['groups'] as $group) {
        $hrefs = [...$hrefs, ...array_column($group['items'], 'href')];
    }

    return $hrefs;
}

const SCHOOL_COMMUNICATION = ['/portal/messages', '/portal/announcements', '/portal/forms', '/academics/requests'];

it('gives a vendor their shop and the Personal group, nothing of the school', function () {
    $vendor = menuPerson(['vendor']);
    $nav = app(BuildNavigationAction::class)->execute($vendor, 'en');

    expect($nav['workspace'])->toBe('vendor')
        ->and(array_column($nav['primary'], 'href'))->toBe(['/vendor'])
        ->and(array_column($nav['groups'], 'key'))->toBe(['me'])
        ->and(array_column($nav['groups'][0]['items'], 'label'))->toBe(['My profile', 'Digital Library', 'My library', 'My wallet', 'Bookstore', 'My orders', 'My wishlist', 'My quotes'])
        ->and(menuHrefs($vendor))->not->toContain('/portal/home', '/learn', '/learn/schedule', '/portal/homework', ...SCHOOL_COMMUNICATION);

    // Home is the shop, and the family home sends them there rather than
    // showing an empty "Student Dashboard".
    expect(app(ResolveWorkspacesAction::class)->execute($vendor)['list'][0]['href'])->toBe('/vendor');
    $this->withoutLocalizationMiddleware()->actingAs($vendor)->get(route('portal.home'))->assertRedirect(route('dashboard'));
    $this->withoutLocalizationMiddleware()->actingAs($vendor)->get(route('dashboard'))->assertRedirect(route('vendor.index'));
});

it('keeps a parent’s own learning out of Family, and a household’s screens out of the School', function () {
    $parent = menuPerson(['parent']);
    $nav = app(BuildNavigationAction::class)->execute($parent, 'en');
    expect(array_column($nav['groups'], 'label'))->toBe(['Communication', 'Education', 'Evaluation', 'Other', 'Personal'])
        ->and(menuHrefs($parent))->toContain('/portal/children', '/portal/invoices', '/portal/pickup', '/portal/exams', '/portal/found-items', ...array_slice(SCHOOL_COMMUNICATION, 0, 3))
        ->not->toContain('/learn', '/learn/schedule', '/teach/schedule', '/write', '/review', '/vendor');

    // A teacher-parent: the School carries the teaching and the school's
    // communication, and none of the family's screens; Family the reverse.
    $teacherParent = menuPerson(['teacher', 'parent']);
    $teacherParent->givePermissionTo('registers.fill');
    expect(menuHrefs($teacherParent, 'school'))->toContain('/teach/schedule', '/portal/messages', '/portal/staff-check-in')
        ->not->toContain('/portal/children', '/portal/invoices', '/portal/pickup', '/portal/absence-notes', '/portal/events', '/portal/movements', '/learn')
        ->and(menuHrefs($teacherParent, 'family'))->toContain('/portal/children', '/portal/invoices', '/portal/pickup')
        ->not->toContain('/teach/schedule', '/portal/staff-check-in', '/academics/registers/today', '/learn');
});

it('gives a pupil their own courses under Education, and no parent-only screen', function () {
    $pupil = menuPerson(['student']);
    $nav = app(BuildNavigationAction::class)->execute($pupil, 'en');
    $education = collect($nav['groups'])->firstWhere('key', 'education');

    expect($nav['workspace'])->toBe('learn')
        ->and(array_column($education['items'], 'href'))->toContain('/learn', '/learn/schedule', '/portal/homework')
        ->and(menuHrefs($pupil))->not->toContain('/portal/children', '/portal/pickup', '/portal/movements', '/portal/work');
});

it('gives a writer, the Bookstore office and the Institute nothing of the school’s communication', function (array $roles, string $workspace) {
    $person = menuPerson($roles);
    $nav = app(BuildNavigationAction::class)->execute($person, 'en');

    expect($nav['workspace'])->toBe($workspace)
        ->and(collect($nav['groups'])->pluck('key')->last())->toBe('me')
        ->and(menuHrefs($person))->not->toContain('/portal/home', '/learn', ...SCHOOL_COMMUNICATION);
})->with([
    'a writer' => [['writer'], 'writing'],
    'a reviewer' => [['reviewer'], 'writing'],
    'the Bookstore office' => [['bookshop_manager'], 'bookstore'],
    'the system admin' => [['super_admin'], 'institute'],
    'a course creator' => [['course_creator'], 'catalog'],
]);

it('keeps the family home for families and pupils', function () {
    $teacher = menuPerson(['teacher']);
    $teacher->givePermissionTo('registers.fill');
    $this->withoutLocalizationMiddleware()->actingAs($teacher)->get(route('portal.home'))->assertRedirect(route('dashboard'));

    foreach ([menuPerson(['parent']), menuPerson(['student'])] as $person) {
        $this->withoutLocalizationMiddleware()->actingAs($person)->get(route('portal.home'))->assertOk();
    }

    // A person with no role has a home of their own since ID2b: My account.
    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())->get(route('portal.home'))->assertRedirect(route('dashboard'));
});

it('marks every screen in the map that is a Blade page for a full page load', function () {
    // An Inertia visit to a Blade route gets a non-Inertia response, and the
    // shell shows it in a modal. The Personal group's seven Blade screens —
    // the Library, the wallet, the Bookstore — were plain visits until ID1.
    // Asked as Inertia asks, every screen a person can open must answer as
    // Inertia, or carry `hard`.
    $everyone = menuPerson(['super_admin', 'headmaster', 'teacher', 'parent', 'student', 'vendor', 'writer', 'reviewer', 'course_creator', 'bookshop_manager']);
    $everyone->givePermissionTo(Permission::all());
    makeYear();
    makeTeacherRow()->forceFill(['user_id' => $everyone->id])->save();

    $items = [];
    foreach ([...NavigationMap::primary(), ...array_map(fn (array $group) => $group['items'], NavigationMap::groups())] as $list) {
        foreach ($list as $item) {
            $items[$item['href']] = ($items[$item['href']] ?? false) || ! empty($item['hard']);
        }
    }

    $version = (string) app(HandleInertiaRequests::class)->version(Request::create('/'));
    $asInertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $version, 'X-Requested-With' => 'XMLHttpRequest'];
    $bladeAsVisit = [];
    $checked = 0;
    foreach ($items as $href => $hard) {
        if ($hard) {
            continue;
        }
        $response = $this->withoutLocalizationMiddleware()->actingAs($everyone)->withHeaders($asInertia)->get($href);
        for ($hop = 0; $hop < 3 && $response->isRedirect(); $hop++) {
            $response = $this->withoutLocalizationMiddleware()->actingAs($everyone)->withHeaders($asInertia)->get(parse_url($response->headers->get('Location'), PHP_URL_PATH) ?? '/');
        }
        if ($response->getStatusCode() !== 200) {
            continue;
        }
        $checked++;
        if ($response->headers->get('X-Inertia') !== 'true') {
            $bladeAsVisit[] = $href;
        }
    }

    expect($checked)->toBeGreaterThan(100)
        ->and($bladeAsVisit)->toBe([], 'Blade screens the shell would open as an Inertia visit (a modal): '.implode(', ', $bladeAsVisit));
});

it('labels the new groups in Dhivehi and Arabic', function () {
    foreach (['teaching', 'communication', 'education', 'evaluation', 'other', 'my_work', 'me', 'profile'] as $key) {
        foreach (['dv', 'ar'] as $locale) {
            expect(trans('nav.'.$key, [], $locale))->not->toBe('nav.'.$key)->not->toBe(trans('nav.'.$key, [], 'en'));
        }
    }
    expect(trans('nav.me'))->toBe('Personal')
        ->and(trans('nav.mine'))->toBe('nav.mine')
        ->and(trans('nav.learn_group'))->toBe('nav.learn_group');
});
