<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The translations editor, the operator checklist and the OTP abuse list in
 * Dhivehi and Arabic (BACKLOG C21, slice SY1, STATUS §5qu).
 *
 * The three system screens read no phrase book: every word on them was
 * English, and so was what the server said when a correction or a tick was
 * refused — which the pages said nowhere. What tripped an OTP limit and the
 * channel it came on were the page's own English or a code. The checklist's
 * items and the strings the editor edits are the operator's and the books'
 * own, and stay as they are.
 */
uses(RefreshDatabase::class);

/** The screens; each reads the `admin` book as `t`. */
function systemScreens(): array
{
    return ['Settings/Translations', 'Settings/Operations', 'Identity/OtpAbuse'];
}

/** Where the server writes what those screens say. */
function systemServerFiles(): array
{
    return [
        'app/Domains/Settings/Http/Controllers/Admin/TranslationController.php',
        'app/Domains/Settings/Http/Controllers/Admin/OperationsController.php',
        'app/Domains/Identity/Http/Controllers/OtpAbuseController.php',
        'app/Domains/Settings/Actions/SaveTranslationOverrideAction.php',
        'app/Domains/Settings/Actions/SuggestTranslationAction.php',
        'app/Domains/Settings/Actions/ToggleOperatorCheckAction.php',
        'app/Domains/Settings/Actions/ListTranslationCatalogAction.php',
    ];
}

function adminBookForSy1(string $locale): array
{
    return require base_path("resources/lang/{$locale}/admin.php");
}

it('keys every string on the system screens in three languages', function () {
    [$en, $dv, $ar] = [adminBookForSy1('en'), adminBookForSy1('dv'), adminBookForSy1('ar')];

    foreach (systemScreens() as $screen) {
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: admin.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: admin.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: admin.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: admin.{$key} says something else in English than the screen")
                ->and($dv[$key])->not->toBe($en[$key], "admin.{$key} is English in Dhivehi")
                ->and($ar[$key])->not->toBe($en[$key], "admin.{$key} is English in Arabic");
        }

        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name")
            ->and(routerVisitsWithoutRow("resources/js/Pages/{$screen}.jsx"))->toBe([], "{$screen} posts with nowhere to say a refusal");
    }
});

it('names what tripped an OTP limit and the channel it came on, in all three languages', function () {
    $keys = [
        ...array_map(fn ($kind) => 'admin.otp_kind_'.$kind, ['send_rate', 'resend_cooldown', 'verify_rate', 'code_attempts']),
        ...array_map(fn ($channel) => 'admin.otp_channel_'.$channel, ['email', 'sms', 'mobile']),
        'admin.tr_language_dv', 'admin.tr_language_ar',
    ];
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('leaves no English in what the server says on the system screens, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (systemServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(systemServerFiles());
    expect($keys)->toContain('admin.error_translation_group', 'admin.error_translation_key', 'admin.error_checklist_item', 'admin.error_translation_locale');
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('serves the system screens in Dhivehi, and refuses an unknown correction or tick in Dhivehi', function () {
    $admin = actingSystemAdmin(['operations.manage', 'translations.manage']);
    $dv = adminBookForSy1('dv');
    app()->setLocale('dv');

    foreach ([
        ['admin.translations.index', 'Settings/Translations', 'tr_intro'],
        ['admin.operations.index', 'Settings/Operations', 'ops_heading'],
        ['admin.users.otp-abuse', 'Identity/OtpAbuse', 'otp_abuse_title'],
    ] as [$route, $component, $key]) {
        $this->withoutLocalizationMiddleware()->actingAs($admin)
            ->get(route($route))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component)->where("t.{$key}", $dv[$key]));
    }

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), ['group' => 'no-such-book', 'key' => 'title', 'value' => 'x', 'locale' => 'dv'])
        ->assertSessionHasErrors(['group' => $dv['error_translation_group']]);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.translations.save'), ['group' => 'common', 'key' => 'no_such_key', 'value' => 'x', 'locale' => 'dv'])
        ->assertSessionHasErrors(['key' => $dv['error_translation_key']]);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->postJson(route('admin.translations.suggest'), ['group' => 'common', 'key' => 'no_such_key', 'locale' => 'dv'])
        ->assertStatus(422)->assertJsonPath('errors.key.0', $dv['error_translation_key']);
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('admin.operations.toggle', 'no-such-item'))
        ->assertSessionHasErrors(['item' => $dv['error_checklist_item']]);
});
