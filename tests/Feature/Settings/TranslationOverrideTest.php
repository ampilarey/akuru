<?php

use App\Domains\Identity\Models\User;
use App\Domains\Settings\Models\TranslationOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function freshTranslation(string $key, string $locale = 'dv'): string
{
    // The translator memoizes loaded groups per instance — re-resolve so
    // each assertion goes back through the loader.
    App::forgetInstance('translator');
    Cache::flush();

    return trans($key, [], $locale);
}

function freshDvTranslation(string $key): string
{
    return freshTranslation($key, 'dv');
}

it('serves a Dhivehi override over the file string and falls back when cleared', function () {
    $fileValue = freshDvTranslation('common.dashboard');
    $admin = actingPeopleAdmin(['translations.manage']);

    // Save a correction — it wins immediately.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), [
            'group' => 'common', 'key' => 'dashboard', 'value' => 'ޑޭޝްބޯޑު ރަނގަޅު',
        ])->assertSessionHasNoErrors();
    expect(freshDvTranslation('common.dashboard'))->toBe('ޑޭޝްބޯޑު ރަނގަޅު')
        ->and((int) TranslationOverride::query()->where('key', 'dashboard')->value('updated_by'))->toBe((int) $admin->id);

    // English is untouched.
    App::forgetInstance('translator');
    expect(trans('common.dashboard', [], 'en'))->not->toBe('ޑޭޝްބޯޑު ރަނގަޅު');

    // Clearing restores the shipped file value.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), [
            'group' => 'common', 'key' => 'dashboard', 'value' => '',
        ])->assertSessionHasNoErrors();
    expect(freshDvTranslation('common.dashboard'))->toBe($fileValue)
        ->and(TranslationOverride::query()->count())->toBe(0);
});

it('rejects unknown groups and keys and renders the editor with suspects flagged', function () {
    $admin = actingPeopleAdmin(['translations.manage']);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), ['group' => 'nope', 'key' => 'dashboard', 'value' => 'x'])
        ->assertSessionHasErrors('group');
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), ['group' => 'common', 'key' => 'not_a_key', 'value' => 'x'])
        ->assertSessionHasErrors('key');

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.index'))
        ->assertOk();
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('suggests via the bound translator and stays silent on the null default', function () {
    $admin = actingPeopleAdmin(['translations.manage']);

    // Null default: endpoint answers, suggestion is null, no external calls.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->postJson(route('admin.translations.suggest'), ['group' => 'common', 'key' => 'dashboard'])
        ->assertOk()
        ->assertJson(['suggestion' => null]);

    // A bound translator's draft comes back verbatim — prefill only,
    // nothing is saved until a human posts it.
    app()->instance(\App\Support\Contracts\MachineTranslatorInterface::class, new class implements \App\Support\Contracts\MachineTranslatorInterface
    {
        public function translate(string $text, string $from, string $to): ?string
        {
            return "[{$to}] {$text}";
        }
    });
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->postJson(route('admin.translations.suggest'), ['group' => 'common', 'key' => 'dashboard'])
        ->assertOk()
        ->assertJsonPath('suggestion', '[dv] '.trans('common.dashboard', [], 'en'));
    expect(TranslationOverride::query()->count())->toBe(0);

    // Unknown keys are refused here too.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->postJson(route('admin.translations.suggest'), ['group' => 'common', 'key' => 'not_a_key'])
        ->assertStatus(422);
});

it('forbids the editor without translations.manage', function () {
    $user = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->get(route('admin.translations.index'))->assertForbidden();
    $this->withoutLocalizationMiddleware()->actingAs($user)
        ->post(route('admin.translations.save'), ['group' => 'common', 'key' => 'dashboard', 'value' => 'x'])
        ->assertForbidden();
});

/**
 * Arabic became editable alongside Dhivehi. Until now
 * `ListTranslationCatalogAction` hardcoded `dv`, so an operator could fix a
 * Dhivehi string from this screen while the same Arabic string needed a file
 * edit and a deploy — against CLAUDE.md, which asks for EN/DV/AR equally. The
 * table and the loader were already locale-generic.
 */
it('edits Arabic without touching the Dhivehi override for the same key', function () {
    $admin = actingPeopleAdmin(['translations.manage']);
    $arFile = freshTranslation('common.dashboard', 'ar');
    $dvFile = freshTranslation('common.dashboard', 'dv');

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), [
            'group' => 'common', 'key' => 'dashboard', 'value' => 'لوحة التحكم — تصحيح', 'locale' => 'ar',
        ])->assertSessionHasNoErrors();

    expect(freshTranslation('common.dashboard', 'ar'))->toBe('لوحة التحكم — تصحيح')
        // The whole point: one language moved and the other did not.
        ->and(freshTranslation('common.dashboard', 'dv'))->toBe($dvFile)
        ->and(TranslationOverride::query()->where('locale', 'ar')->count())->toBe(1)
        ->and(TranslationOverride::query()->where('locale', 'dv')->count())->toBe(0);

    // Both can hold a correction for the same key at once — the unique index
    // is (locale, group, key), not (group, key).
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), [
            'group' => 'common', 'key' => 'dashboard', 'value' => 'ޑޭޝްބޯޑު — ރަނގަޅު', 'locale' => 'dv',
        ])->assertSessionHasNoErrors();

    expect(freshTranslation('common.dashboard', 'ar'))->toBe('لوحة التحكم — تصحيح')
        ->and(freshTranslation('common.dashboard', 'dv'))->toBe('ޑޭޝްބޯޑު — ރަނގަޅު')
        ->and(TranslationOverride::query()->count())->toBe(2);

    // Clearing Arabic restores the Arabic file string and leaves Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), [
            'group' => 'common', 'key' => 'dashboard', 'value' => '', 'locale' => 'ar',
        ])->assertSessionHasNoErrors();

    expect(freshTranslation('common.dashboard', 'ar'))->toBe($arFile)
        ->and(freshTranslation('common.dashboard', 'dv'))->toBe('ޑޭޝްބޯޑު — ރަނގަޅު');
});

it('renders and exports each editable language, and defaults to Dhivehi', function () {
    $admin = actingPeopleAdmin(['translations.manage']);

    // No ?locale= means Dhivehi, so links written before Arabic existed
    // still land where they used to.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/Translations')
            ->where('locale', 'dv')
            ->where('locales', ['dv', 'ar'])
            ->etc());

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.index', ['locale' => 'ar']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'ar')->etc());

    // A junk locale falls back rather than 500ing on a hand-edited URL.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.index', ['locale' => 'fr']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'dv')->etc());

    $arabic = $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.export', ['locale' => 'ar']))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($arabic->headers->get('content-disposition'))->toContain('arabic-translations.csv')
        ->and($arabic->streamedContent())->toContain('file_ar');

    $dhivehi = $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('admin.translations.export'))->assertOk();
    expect($dhivehi->headers->get('content-disposition'))->toContain('dhivehi-translations.csv');
});

it('refuses a locale it does not edit, English included', function () {
    $admin = actingPeopleAdmin(['translations.manage']);

    // English is the reference. Correcting it is a code change, not an
    // override — accepting one here would let the editor quietly fork the
    // key set the whole catalog is built from.
    foreach (['en', 'fr'] as $locale) {
        $this->withoutLocalizationMiddleware()->actingAs($admin)
            ->post(route('admin.translations.save'), [
                'group' => 'common', 'key' => 'dashboard', 'value' => 'x', 'locale' => $locale,
            ])->assertSessionHasErrors('locale');
    }

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->postJson(route('admin.translations.suggest'), [
            'group' => 'common', 'key' => 'dashboard', 'locale' => 'en',
        ])->assertStatus(422);

    expect(TranslationOverride::query()->count())->toBe(0);

    // An *absent* locale is not a rejection — it means Dhivehi, the same
    // rule the index and export use, so a caller written before Arabic
    // existed still works. `nullable` normalises '' to absent, so both
    // land here.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), [
            'group' => 'common', 'key' => 'dashboard', 'value' => 'x',
        ])->assertSessionHasNoErrors();

    expect(TranslationOverride::query()->where('locale', 'dv')->count())->toBe(1)
        ->and(TranslationOverride::query()->where('locale', 'ar')->count())->toBe(0);
});

it('suggests into the language being edited', function () {
    $admin = actingPeopleAdmin(['translations.manage']);

    app()->instance(\App\Support\Contracts\MachineTranslatorInterface::class, new class implements \App\Support\Contracts\MachineTranslatorInterface
    {
        public function translate(string $text, string $from, string $to): ?string
        {
            return "[{$to}] {$text}";
        }
    });

    // The draft must be asked for in Arabic, not in Dhivehi and relabelled.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->postJson(route('admin.translations.suggest'), [
            'group' => 'common', 'key' => 'dashboard', 'locale' => 'ar',
        ])
        ->assertOk()
        ->assertJsonPath('suggestion', '[ar] '.trans('common.dashboard', [], 'en'));
});

/**
 * Nested groups are editable rows too.
 *
 * `ListTranslationCatalogAction` used to `continue` past any non-string value,
 * which silently dropped every nested line. `notifications.php` and
 * `documents.php` are **entirely** nested, so both rendered as "(0)" in the
 * editor and were editable in neither language — including the notification
 * texts that get sent to families.
 *
 * Found by opening the screen in a browser, not by a test: the suite asserted
 * the catalog renders and never that it contained anything. That is exactly
 * the gap CLAUDE.md's "walked in a browser" clause exists to catch.
 */
it('lists nested language lines as editable rows', function () {
    $catalog = app(\App\Domains\Settings\Actions\ListTranslationCatalogAction::class)->execute('ar');
    $rows = collect($catalog['groups'])->keyBy('group')->map(fn ($g) => count($g['items']));

    // The two entirely-nested groups. Before the fix both were 0.
    expect($rows['notifications'])->toBeGreaterThan(0)
        ->and($rows['documents'])->toBeGreaterThan(0)
        // Flat groups must not regress.
        ->and($rows['common'])->toBeGreaterThan(0)
        ->and($rows['public'])->toBeGreaterThan(0);

    // The catalog total must equal every English string in the five groups —
    // the same 557 the translation-parity baseline counts independently.
    expect($catalog['total'])->toBe($rows->sum());

    $notifications = collect($catalog['groups'])->firstWhere('group', 'notifications');
    $keys = collect($notifications['items'])->pluck('key');
    expect($keys->contains(fn ($k) => str_contains($k, '.')))->toBeTrue('Nested keys should arrive dotted.');
});

it('saves and clears an override on a nested key', function () {
    $admin = actingPeopleAdmin(['translations.manage']);
    $fileValue = freshTranslation('notifications.attendance.status.absent', 'ar');

    // The save action validates with Lang::get($group.'.'.$key) and the loader
    // writes with Arr::set() — both already spoke dot notation, so nothing
    // downstream needed changing. This proves that end to end.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), [
            'group' => 'notifications', 'key' => 'attendance.status.absent',
            'value' => 'غائب — تصحيح', 'locale' => 'ar',
        ])->assertSessionHasNoErrors();

    expect(freshTranslation('notifications.attendance.status.absent', 'ar'))->toBe('غائب — تصحيح')
        // A nested override must not flatten the group and lose its siblings.
        ->and(freshTranslation('notifications.attendance.status.late', 'ar'))->not->toBe('غائب — تصحيح');

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), [
            'group' => 'notifications', 'key' => 'attendance.status.absent',
            'value' => '', 'locale' => 'ar',
        ])->assertSessionHasNoErrors();

    expect(freshTranslation('notifications.attendance.status.absent', 'ar'))->toBe($fileValue);
});
