<?php

use App\Domains\Identity\Models\User;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\Inertia\Phrases;
use Inertia\Support\Header;
use Spatie\Permission\Models\Role;

/**
 * The phrase books travel once (docs/ADMIN_PANEL.md §7 P2, STATUS §5nm).
 *
 * `t` is a page's whole language file — 44 KB for `admin` — and Inertia
 * resends page props on every visit. As a once-prop it is sent on the first
 * load, remembered by the client under a key, and left out of every visit
 * whose request names that key; the key carries the file and the locale.
 */
function superAdminForOnceProps(): User
{
    Role::findOrCreate('super_admin', 'web');
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    return $user;
}

function inertiaVisitHeaders(array $loadedOnceProps = []): array
{
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request())];
    if ($loadedOnceProps !== []) {
        $headers[Header::EXCEPT_ONCE_PROPS] = implode(',', $loadedOnceProps);
    }

    return $headers;
}

it('sends the phrase book on a first load, under a key that names the file and the locale', function () {
    $response = test()->withoutLocalizationMiddleware()->actingAs(superAdminForOnceProps())
        ->get('/admin/users', inertiaVisitHeaders())
        ->assertOk();

    $page = $response->json();
    expect($page['props']['t']['users_title'] ?? null)->toBe(trans('admin.users_title', [], 'en'))
        ->and($page['props']['i18n']['nav']['more'] ?? null)->toBe(trans('nav.more', [], 'en'))
        ->and($page['onceProps'])->toHaveKeys([Phrases::key('admin', 'en'), 'i18n:en'])
        ->and($page['onceProps'][Phrases::key('admin', 'en')]['expiresAt'])->toBeGreaterThan(now()->getTimestampMs());
});

it('leaves the phrase book out of a visit whose client already holds it', function () {
    $response = test()->withoutLocalizationMiddleware()->actingAs(superAdminForOnceProps())
        ->get('/admin/users', inertiaVisitHeaders([Phrases::key('admin', 'en'), 'i18n:en']))
        ->assertOk();

    $page = $response->json();
    expect($page['props'])->not->toHaveKey('t')
        ->and($page['props'])->not->toHaveKey('i18n')
        ->and($page['props']['users'] ?? null)->not->toBeNull()
        ->and($page['onceProps'])->toHaveKey(Phrases::key('admin', 'en'));
});

it('sends the other language and the other book when the key does not match', function () {
    $user = superAdminForOnceProps();

    // Dhivehi after English: a different key, so the Dhivehi book is sent.
    // (Tests see no locale prefix in the URL, so the locale is set directly.)
    app()->setLocale('dv');
    $dv = test()->withoutLocalizationMiddleware()->actingAs($user)->get('/admin/users', inertiaVisitHeaders([Phrases::key('admin', 'en')]))->assertOk()->json();
    app()->setLocale('en');
    expect($dv['props']['t']['users_title'] ?? null)->toBe(trans('admin.users_title', [], 'dv'))
        ->and($dv['onceProps'])->toHaveKey(Phrases::key('admin', 'dv'));

    // The Bookstore office speaks `shop`, not `admin`: holding `admin` does not cover it.
    $shop = test()->withoutLocalizationMiddleware()->actingAs($user)->get('/admin/bookshop', inertiaVisitHeaders([Phrases::key('admin', 'en')]))->assertOk()->json();
    expect($shop['props']['t'] ?? null)->not->toBeNull()
        ->and($shop['onceProps'])->toHaveKey(Phrases::key('shop', 'en'));
});

it('still gives a full page load everything', function () {
    test()->withoutLocalizationMiddleware()->actingAs(superAdminForOnceProps())
        ->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Identity/Users')->has('t.users_title')->has('i18n.nav.more'));
});
