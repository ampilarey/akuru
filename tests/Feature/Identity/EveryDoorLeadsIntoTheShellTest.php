<?php

use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * docs/SIGN_IN_PLAN.md ID3: every door a signed-in person uses leads into the
 * shell, to their own workspace's home — setting a password (F6), the
 * website's header (F5), the mobile app (F9) — and the sign-in page says so.
 */
function doorPerson(array $roles = [], bool $codeOnly = false): User
{
    test()->seed(\Database\Seeders\RoleSeeder::class);
    $user = User::factory()->create(['password' => Hash::make('correct-horse'), 'force_password_change' => $codeOnly]);
    if ($roles !== []) {
        $user->assignRole($roles);
    }

    return $user;
}

it('asks a code-only person for a password on their workspace home, and brings them back there once it is set', function () {
    $parent = doorPerson(['parent'], codeOnly: true);
    $as = fn () => $this->withoutLocalizationMiddleware()->actingAs($parent);

    $as()->get(route('portal.home'))->assertOk()->assertInertia(fn ($page) => $page->where('auth.must_set_password', true));
    $as()->get(route('account.set-password'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Identity/SetPassword')
            ->where('needs_current_password', false)
            ->where('store_href', '/account/set-password')
            ->where('t.password_save', 'Save password'));

    // Saving returns to their own home through the router, not to the website.
    $as()->post(route('account.set-password.store'), ['password' => 'a-new-one-1', 'password_confirmation' => 'a-new-one-1'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Your password is saved. Next time you can sign in with it, or with a code as before.');
    $as()->get(route('dashboard'))->assertRedirect(route('portal.home'));
    $this->withoutLocalizationMiddleware()->actingAs($parent->fresh())->get(route('portal.home'))
        ->assertInertia(fn ($page) => $page->where('auth.must_set_password', false));
});

it('gives a signed-in person one door from the website into the app, and none to the old portal', function () {
    foreach ([['teacher'], ['parent'], []] as $roles) {
        $this->withoutLocalizationMiddleware()->actingAs(doorPerson($roles))
            ->get(route('public.library.index'))
            ->assertOk()
            ->assertSee('data-testid="nav-my-portal"', false)
            ->assertSee('data-testid="nav-my-portal-mobile"', false)
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('href="'.route('my.enrollments').'"', false)
            ->assertDontSee('/portal/dashboard', false)
            ->assertDontSee('/portal/enrollments', false)
            ->assertDontSee('/portal/payments', false)
            ->assertDontSee('Admin Dashboard', false);
    }
});

it('opens the mobile app on the router, not the marketing home', function () {
    $config = file_get_contents(base_path('capacitor.config.ts'));

    expect($config)->toContain('url: `${serverBase}/dashboard`')
        ->not->toContain("url: process.env.CAPACITOR_SERVER_URL || 'https://akuru.edu.mv',");
});

it('says on the sign-in page where each kind of person lands', function () {
    $this->withoutLocalizationMiddleware()->get(route('login'))->assertOk()
        ->assertSee('data-testid="login-lands"', false)
        ->assertSee('your own space', false);
});
