<?php

use App\Domains\Settings\Contracts\SettingsRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * SPEC §42's "Settings repository/interface" — the one required interface of
 * the ten that had no implementation, which is why ten call sites across
 * Academics, ExamsGrades, Finance and HR read `DB::table('settings')`
 * directly.
 *
 * `RequiredServiceInterfacesTest` pins that the interface exists, is bound and
 * that nothing outside the Settings domain touches the table. This pins what
 * it actually does, which that test cannot: it runs without a database on
 * purpose.
 */
uses(RefreshDatabase::class);

it('takes the default when a key is absent', function () {
    $settings = app(SettingsRepositoryInterface::class);

    expect($settings->get('a.key.that.does.not.exist', 'fallback'))->toBe('fallback');
    expect($settings->many(['nope.one' => 'x', 'nope.two' => null]))
        ->toBe(['nope.one' => 'x', 'nope.two' => null]);
});

it('returns what is stored, including an empty string', function () {
    // Faithful to the ten direct reads this replaced: `pluck` handed an empty
    // stored value straight through, so folding "empty means unset" into the
    // bulk read would have been a quiet behaviour change across four domains.
    // A refactor does not get to decide that.
    DB::table('settings')->insert(['key' => 'probe.empty', 'value' => '', 'created_at' => now(), 'updated_at' => now()]);

    expect(app(SettingsRepositoryInterface::class)->many(['probe.empty' => 'default']))
        ->toBe(['probe.empty' => '']);
});

it('is stricter in the typed readers, which have no callers to surprise', function () {
    $settings = app(SettingsRepositoryInterface::class);

    expect($settings->getInt('nope.int', 7))->toBe(7);
    expect($settings->getBool('nope.bool', true))->toBeTrue();
    expect($settings->getString('nope.string', 'd'))->toBe('d');

    DB::table('settings')->insert([
        ['key' => 'probe.int', 'value' => '42', 'created_at' => now(), 'updated_at' => now()],
        ['key' => 'probe.bool', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect($settings->getInt('probe.int', 0))->toBe(42);
    expect($settings->getBool('probe.bool', false))->toBeTrue();
});
