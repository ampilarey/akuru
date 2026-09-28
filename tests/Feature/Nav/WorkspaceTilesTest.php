<?php

use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Actions\StartMessageThreadAction;
use App\Support\Navigation\BuildNavigationAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * docs/SIGN_IN_PLAN.md ID5: the Family, Learn, Learner, teacher's-day, shop
 * and My account homes lay out the workspace's menu as tiles, Messages first
 * with its unread count. The tiles are drawn from the shared `nav` by the
 * shell's `WorkspaceTiles`, so there is no second list to drift: what the
 * server must guarantee is that each home carries that menu and the count.
 */
function tilesPerson(array $roles): User
{
    test()->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create();
    $user->assignRole($roles);

    return $user;
}

it('gives each workspace home its menu and the Messages count the Messages tile shows', function () {
    $parent = tilesPerson(['parent']);
    $teacher = tilesPerson(['teacher']);
    app(StartMessageThreadAction::class)->execute((int) $teacher->id, [(int) $parent->id], 'Trip', 'The trip is on Thursday.');

    $this->withoutLocalizationMiddleware()->actingAs($parent)->get(route('portal.home'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/Home')
            ->where('auth.unread_messages', 1)
            ->where('nav.groups', fn ($groups) => collect($groups)->flatMap(fn ($group) => array_column($group['items'], 'href'))->contains('/portal/messages')));

    // The teacher, who sent it, has nothing unread; their day carries the School's menu.
    $this->withoutLocalizationMiddleware()->actingAs($teacher)->get(route('portal.teacher'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Portal/TeacherHome')
            ->where('auth.unread_messages', 0)
            ->where('nav.workspace', 'school'));
});

it('offers no tile the menu lacks: a vendor\'s shop home carries only the Personal group', function () {
    $vendor = tilesPerson(['vendor']);
    $nav = app(BuildNavigationAction::class)->execute($vendor, 'en', 'vendor');

    expect(array_column($nav['groups'], 'key'))->toBe(['me'])
        ->and(collect($nav['groups'])->flatMap(fn ($group) => array_column($group['items'], 'href'))->all())
        ->not->toContain('/portal/messages', '/portal/home', '/learn');
});
