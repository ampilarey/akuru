<?php

use App\Domains\Identity\Exceptions\SuperAdminProtectedException;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * A super admin deleted their own account from the profile page and locked
 * themselves out of the platform. `users` has no soft deletes, so nothing
 * could be restored. AdminUserController already refused to delete super
 * admins; the profile page did not.
 */
beforeEach(function () {
    Role::findOrCreate('super_admin', 'web');
});

function superAdminUser(): User
{
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

it('refuses to delete a super admin at the model level', function () {
    $user = superAdminUser();

    expect(fn () => $user->delete())
        ->toThrow(SuperAdminProtectedException::class);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
});

it('blocks a super admin deleting their own account from the profile page', function () {
    $user = superAdminUser();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect(route('profile.edit'));

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();
    // The refusal must not log them out — that was the trap: losing the
    // session and the account in the same request.
    expect(auth()->check())->toBeTrue();
});

it('does not offer the delete form to a super admin', function () {
    $user = superAdminUser();

    // An offered button that then refuses is a trap: the guard must be
    // visible in the UI, not only enforced on submit.
    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('confirm-user-deletion')
        ->assertSee('cannot be deleted', false);
});

it('still offers the delete form to an ordinary user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('confirm-user-deletion', false);
});

it('still lets an ordinary user delete their own account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

it('allows a deliberate deletion through the explicit escape hatch', function () {
    $user = superAdminUser();

    User::allowSuperAdminDeletion(fn () => $user->delete());

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

it('re-arms the guard after the escape hatch, even when the callback throws', function () {
    $first = superAdminUser();
    $second = superAdminUser();

    try {
        User::allowSuperAdminDeletion(function () use ($first) {
            $first->delete();
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // expected
    }

    // The flag must not leak past the failure, or one throwing call would
    // silently disarm the protection for the rest of the request.
    expect(fn () => $second->delete())->toThrow(SuperAdminProtectedException::class);
});

it('keeps a user with the role removed deletable', function () {
    $user = superAdminUser();
    $user->removeRole('super_admin');

    $user->delete();

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});
