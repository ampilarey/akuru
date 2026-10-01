<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('rejects GET logout and logs out with POST from an Inertia session', function () {
    $admin = actingPeopleAdmin();
    makeYear();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get('/logout')
        ->assertStatus(405);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.years.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Years/Index')
            ->where('auth.user.id', $admin->id)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();
});

// The shell's button posts as an Inertia request; the home page is Blade. A plain
// redirect came back as a non-Inertia page and Inertia showed it in a modal over
// the shell (the owner's screenshot, 2026-10-01). Inertia::location makes the
// browser leave the shell for real.
it('tells an Inertia logout to do a full visit to the home page', function () {
    $admin = actingPeopleAdmin();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('logout'), [], ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', '/');

    $this->assertGuest();
});

it('posts logout from AppShell instead of linking GET /logout', function () {
    $shell = file_get_contents(resource_path('js/Layouts/AppShell.jsx'));

    expect($shell)->toContain("router.post('/logout')")
        ->and($shell)->toContain('Log out')
        ->and($shell)->not->toContain('href="/logout"')
        ->and($shell)->not->toContain("href={'/logout'}");
});
