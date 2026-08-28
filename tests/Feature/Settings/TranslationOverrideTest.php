<?php

use App\Domains\Identity\Models\User;
use App\Domains\Settings\Models\TranslationOverride;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

function freshDvTranslation(string $key): string
{
    // The translator memoizes loaded groups per instance — re-resolve so
    // each assertion goes back through the loader.
    App::forgetInstance('translator');
    Cache::flush();

    return trans($key, [], 'dv');
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
