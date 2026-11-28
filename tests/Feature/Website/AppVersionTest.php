<?php

use App\Domains\Identity\Models\User;
use App\Support\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * PW2 (STATUS §5va): the running version is on every signed-in page and on
 * System Settings, so the owner can see that a pull took.
 */
uses(RefreshDatabase::class);

function pw2Checkout(array $files): string
{
    $root = sys_get_temp_dir().'/akuru-version-'.bin2hex(random_bytes(4));
    foreach ($files as $path => $body) {
        @mkdir(dirname($root.'/'.$path), 0777, true);
        file_put_contents($root.'/'.$path, $body);
    }

    return $root;
}

it('reads the commit from a branch, a packed ref and a detached head, with when it landed', function () {
    $sha = str_repeat('a1b2c3d4', 5);

    $branch = pw2Checkout(['.git/HEAD' => "ref: refs/heads/main\n", '.git/refs/heads/main' => $sha."\n"]);
    touch($branch.'/.git/refs/heads/main', 1795860000);
    expect(AppVersion::current($branch)['id'])->toBe('a1b2c3d')
        ->and(AppVersion::current($branch)['at']->getTimestamp())->toBe(1795860000);

    $packed = pw2Checkout(['.git/HEAD' => "ref: refs/heads/main\n", '.git/packed-refs' => "# pack-refs\n".str_repeat('f', 40)." refs/heads/other\n{$sha} refs/heads/main\n"]);
    expect(AppVersion::current($packed)['id'])->toBe('a1b2c3d');

    $detached = pw2Checkout(['.git/HEAD' => $sha."\n"]);
    expect(AppVersion::current($detached)['id'])->toBe('a1b2c3d');
});

it('falls back to the build without a checkout, and reads nothing through a crooked ref', function () {
    $zip = pw2Checkout(['public/build/manifest.json' => '{"a":1}']);
    expect(AppVersion::current($zip)['id'])->toBe(substr(hash('sha256', '{"a":1}'), 0, 7));

    $crooked = pw2Checkout(['.git/HEAD' => "ref: refs/../../secret\n", 'secret' => str_repeat('b', 40)]);
    expect(AppVersion::current($crooked)['id'])->toBe('');
    expect(AppVersion::current(pw2Checkout(['readme' => 'x'])))->toBe(['id' => '', 'at' => null]);
});

it('shows the version to whoever is signed in, on System Settings too, and to nobody else', function () {
    $web = test()->withoutLocalizationMiddleware();
    $label = AppVersion::label();
    expect($label)->toStartWith('Version ')->toContain(AppVersion::current()['id']);

    $web->actingAs(actingSystemAdmin())->get('/admin/settings/system')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('app_version', $label)->has('t.version_heading')->has('t.version_hint'));
    auth()->logout();
    $guest = test()->withoutLocalizationMiddleware()->get('/learn');
    if ($guest->isOk()) {
        $guest->assertInertia(fn (Assert $page) => $page->where('app_version', ''));
    }
    foreach (['dv', 'ar'] as $locale) {
        app()->setLocale($locale);
        expect(AppVersion::label())->not->toStartWith('Version ')->toContain(AppVersion::current()['id']);
    }
});
