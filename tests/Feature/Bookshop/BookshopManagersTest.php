<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * B10b: a Bookstore admin (`bookshop_manager`) runs /admin/bookshop without
 * being an admin of the school system; full admins add and remove them —
 * an existing account by email, or a new one with a one-time password
 * shown once; a command does it from the server.
 */
function teamAs(User $user)
{
    return test()->withoutLocalizationMiddleware()->actingAs($user);
}

function fullAdmin(): User
{
    Permission::findOrCreate('bookshop.manage', 'web');
    Role::findOrCreate('admin', 'web')->givePermissionTo('bookshop.manage');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    return $admin;
}

it('lets a Bookstore admin run the bookstore screen and nothing of the school system', function () {
    fullAdmin();
    $manager = User::factory()->create();
    $manager->assignRole('bookshop_manager');

    expect($manager->can('bookshop.manage'))->toBeTrue();
    teamAs($manager)->get(route('admin.bookshop.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Bookshop/Admin')->where('team.can_manage', false));
    teamAs($manager)->get(route('dashboard'))->assertRedirect(route('admin.bookshop.index'));
    teamAs($manager)->get('/admin/operations')->assertForbidden();

    // The office screen's writes are open to them; managing the team is not.
    teamAs($manager)->post(route('admin.bookshop.categories.store'), ['name' => 'Workbooks', 'slug' => 'workbooks'])->assertSessionHasNoErrors();
    teamAs($manager)->post(route('admin.bookshop.team.store'), ['email' => 'x@example.test', 'name' => 'X'])->assertForbidden();

    // Someone with no role is still kept out.
    teamAs(User::factory()->create())->get(route('admin.bookshop.index'))->assertForbidden();
});

it('lets a full admin add an existing account or a new one, and remove them', function () {
    $admin = fullAdmin();
    $existing = User::factory()->create(['email' => 'aisha@example.test', 'name' => 'Aisha']);

    teamAs($admin)->post(route('admin.bookshop.team.store'), ['email' => 'Aisha@Example.test'])->assertSessionHasNoErrors()->assertSessionMissing('team_added.password');
    expect($existing->refresh()->hasRole('bookshop_manager'))->toBeTrue();

    teamAs($admin)->post(route('admin.bookshop.team.store'), ['email' => 'new@example.test'])->assertSessionHasErrors('name');
    teamAs($admin)->post(route('admin.bookshop.team.store'), ['email' => 'new@example.test', 'name' => 'Hassan'])->assertSessionHas('team_added', fn ($added) => $added['email'] === 'new@example.test' && strlen($added['password']) === 12);
    $new = User::query()->where('email', 'new@example.test')->sole();
    expect($new->hasRole('bookshop_manager'))->toBeTrue()->and((bool) $new->force_password_change)->toBeTrue();

    teamAs($admin)->get(route('admin.bookshop.index'))->assertInertia(fn ($page) => $page->where('team.can_manage', true)->has('team.members', 2)->where('team.members.0.name', 'Aisha'));

    teamAs($admin)->post(route('admin.bookshop.team.remove', $existing->id))->assertSessionHasNoErrors();
    expect($existing->refresh()->hasRole('bookshop_manager'))->toBeFalse();
    teamAs($existing)->get(route('admin.bookshop.index'))->assertForbidden();
});

it('makes a Bookstore admin from the server, only of an existing account', function () {
    fullAdmin();
    $user = User::factory()->create(['email' => 'office@example.test']);

    $this->artisan('bookshop:grant-manager', ['email' => 'nobody@example.test'])->assertFailed();
    $this->artisan('bookshop:grant-manager', ['email' => 'office@example.test'])->assertSuccessful();
    expect($user->refresh()->hasRole('bookshop_manager'))->toBeTrue();
    $this->artisan('bookshop:grant-manager', ['email' => 'office@example.test', '--revoke' => true])->assertSuccessful();
    expect($user->refresh()->hasRole('bookshop_manager'))->toBeFalse();
});
