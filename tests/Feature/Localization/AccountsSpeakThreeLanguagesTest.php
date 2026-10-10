<?php

use App\Domains\Identity\Actions\LinkAccountAction;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\OtpService;
use App\Domains\Identity\Support\Wait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

/**
 * A person's linked accounts, the sign-in codes' refusals and a teacher's
 * schedule in Dhivehi and Arabic (BACKLOG C21, slice AC1, STATUS §5qs).
 *
 * The linked accounts page, which every account has, and a teacher's
 * schedule read no phrase book: every word on them was English, and so was
 * what the server said when an account was linked, switched or unlinked, or
 * refused. The sign-in codes' fourteen refusals were English on every page
 * that asks for one, and a wait read *1 minute* in any language — the
 * registration's printed raw seconds. A refused Switch or Unlink was said
 * nowhere, and of the link form only the sign-in details' refusal was said.
 */
uses(RefreshDatabase::class);

/** Each screen and the book its phrases are `t.key || 'English'` from. */
function accountScreens(): array
{
    return ['Identity/LinkedAccounts' => 'account', 'Offerings/Teacher/Schedule' => 'teach'];
}

/** Where the server writes what those screens, and a code's refusal, say. */
function accountServerFiles(): array
{
    return [
        'app/Domains/Identity/Http/Controllers/LinkedAccountController.php',
        'app/Domains/Identity/Actions/LinkAccountAction.php',
        'app/Domains/Identity/Actions/SwitchAccountAction.php',
        'app/Domains/Identity/Actions/UnlinkAccountAction.php',
        'app/Domains/Identity/Services/OtpService.php',
        'app/Domains/Identity/Support/Wait.php',
        'app/Domains/Offerings/Http/Controllers/TeacherScheduleController.php',
    ];
}

it('keys every string on the linked accounts and schedule screens in three languages', function () {
    foreach (accountScreens() as $screen => $book) {
        [$en, $dv, $ar] = array_map(fn (string $locale) => require base_path("resources/lang/{$locale}/{$book}.php"), ['en', 'dv', 'ar']);
        $source = file_get_contents(resource_path("js/Pages/{$screen}.jsx"));
        preg_match_all("/(?<![\\w\$.])t\\.([a-z][a-z0-9_]+) \\|\\| '((?:[^'\\\\]|\\\\.)*)'/", $source, $uses, PREG_SET_ORDER);
        expect($uses)->not->toBeEmpty("{$screen} uses no phrases");

        foreach ($uses as [, $key, $fallback]) {
            expect(array_key_exists($key, $en))->toBeTrue("{$screen}: {$book}.{$key} is missing in English")
                ->and(array_key_exists($key, $dv))->toBeTrue("{$screen}: {$book}.{$key} is missing in Dhivehi")
                ->and(array_key_exists($key, $ar))->toBeTrue("{$screen}: {$book}.{$key} is missing in Arabic")
                ->and($en[$key])->toBe(stripslashes($fallback), "{$screen}: {$book}.{$key} says something else in English than the screen")
                ->and($dv[$key])->not->toBe($en[$key], "{$book}.{$key} is English in Dhivehi")
                ->and($ar[$key])->not->toBe($en[$key], "{$book}.{$key} is English in Arabic");
        }

        expect(preg_match_all('/>\s*[A-Z][A-Za-z]+[^<>{}]*</', $source, $text))->toBe(0, "{$screen} has English text nodes: ".implode(' | ', $text[0] ?? []))
            ->and(preg_match_all('/(placeholder|aria-label|title|data-label)="[A-Za-z][^"]*"/', $source, $attrs))->toBe(0, "{$screen} has English attributes: ".implode(' | ', $attrs[0] ?? []))
            ->and(unnamedFields($source))->toBe([], "{$screen} has fields with no name")
            ->and(routerVisitsWithoutRow("resources/js/Pages/{$screen}.jsx"))->toBe([], "{$screen} posts with nowhere to say a refusal");
    }
});

it('leaves no English in what the server says on them, or when a code is refused, and says it in Dhivehi and Arabic', function () {
    $english = [];
    foreach (accountServerFiles() as $file) {
        $english = [...$english, ...refusalEnglishIn($file)];
    }
    expect($english)->toBe([]);

    $keys = refusalKeysIn(accountServerFiles());
    expect($keys)->toContain('account.flash_linked', 'account.error_link_no_match', 'account.error_account_not_linked',
        'account.error_otp_wait', 'account.error_otp_invalid', 'account.wait_minutes');
    foreach ($keys as $key) {
        expect(trans($key, [], 'en'))->not->toBe($key, "{$key} has no English")
            ->and(trans($key, [], 'dv'))->toMatch('/\p{Thaana}/u', "{$key} in Dhivehi")
            ->and(trans($key, [], 'ar'))->toMatch('/\p{Arabic}/u', "{$key} in Arabic");
    }
});

it('says a wait in the page’s language, and the same in English as before', function () {
    expect(Wait::describe(60))->toBe('1 minute')
        ->and(Wait::describe(45))->toBe('45 seconds');

    app()->setLocale('dv');
    expect(Wait::describe(60))->toBe('1 މިނެޓު')
        ->and(Wait::describe(120))->toBe('2 މިނެޓު')
        ->and(Wait::describe(45))->toBe('45 ސިކުންތު');

    app()->setLocale('ar');
    expect(Wait::describe(60))->toBe('دقيقة واحدة');
});

it('serves the linked accounts and schedule screens in Dhivehi, and says what was done and refused in Dhivehi', function () {
    Role::findOrCreate('teacher', 'web');
    Role::findOrCreate('parent', 'web');
    $teacher = User::factory()->create(['name' => 'Aminath Teacher', 'password' => Hash::make('teacher-pass')]);
    $teacher->assignRole('teacher');
    $parent = User::factory()->create(['name' => 'Aminath Parent', 'password' => Hash::make('parent-pass')]);
    $parent->assignRole('parent');
    $stranger = User::factory()->create();
    RateLimiter::clear('link-account|'.$teacher->id.'|127.0.0.1');
    $dv = require base_path('resources/lang/dv/account.php');
    app()->setLocale('dv');

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->get(route('account.linked'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Identity/LinkedAccounts')->where('t.linked_title', $dv['linked_title']));

    // A wrong password is refused in Dhivehi; the right one links, said so.
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('account.linked.store'), ['identifier' => $parent->email, 'password' => 'wrong'])
        ->assertSessionHasErrors(['identifier' => $dv['error_link_no_match']]);
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('account.linked.store'), ['identifier' => $parent->email, 'password' => 'parent-pass'])
        ->assertSessionHas('success', __('account.flash_linked', ['name' => 'Aminath Parent'], 'dv'));

    // Switching to, or unlinking, an account that is not linked is refused in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->post(route('account.switch', $stranger->id))
        ->assertSessionHasErrors(['account' => $dv['error_account_not_linked']]);
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->delete(route('account.linked.destroy', $stranger->id))
        ->assertSessionHasErrors(['account' => $dv['error_account_not_linked']]);

    // Unlinked, said in Dhivehi.
    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->delete(route('account.linked.destroy', $parent->id))
        ->assertSessionHas('success', $dv['flash_unlinked']);

    // Too many tries are refused with the wait in Dhivehi.
    $key = 'link-account|'.$teacher->id.'|127.0.0.1';
    foreach (range(1, 5) as $ignored) {
        RateLimiter::hit($key, 300);
    }
    try {
        app(LinkAccountAction::class)->execute($teacher, $parent->email, 'parent-pass', '127.0.0.1');
        $this->fail('too many tries should be refused');
    } catch (ValidationException $e) {
        expect($e->errors()['identifier'][0])->toMatch('/\p{Thaana}/u')->not->toMatch('/[A-Za-z]{3,}/');
    }

    $this->withoutLocalizationMiddleware()->actingAs($teacher)
        ->get(route('teach.schedule'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Offerings/Teacher/Schedule')
            ->where('t.schedule_title', __('teach.schedule_title', [], 'dv')));
});

it('refuses a wrong sign-in code, and a code asked for too soon, in Dhivehi', function () {
    config()->set('otp.resend_cooldown_seconds', 60);
    $user = User::factory()->create();
    $contact = UserContact::query()->create(['user_id' => $user->id, 'type' => 'email', 'value' => 'ac1@example.test', 'verified_at' => now()]);
    $service = app(OtpService::class);
    RateLimiter::clear('otp:send:'.$contact->id.':login');
    RateLimiter::clear('otp:cooldown:'.$contact->id.':login');
    app()->setLocale('dv');

    $service->send($contact, 'login');
    try {
        $service->send($contact, 'login');
        $this->fail('the cooldown should have refused');
    } catch (ValidationException $e) {
        expect($e->errors()['contact'][0])->toMatch('/\p{Thaana}/u')->not->toMatch('/[A-Za-z]{3,}/');
    }

    try {
        $service->verify($contact, 'login', '000000');
        $this->fail('a wrong code should have been refused');
    } catch (ValidationException $e) {
        expect($e->errors()['code'][0])->toBe(__('account.error_otp_invalid', [], 'dv'));
    }
});

it('says the registration code’s wait the way a person would, in the page’s language', function () {
    config()->set('otp.resend_cooldown_seconds', 120);
    $service = app(OtpService::class);
    $value = 'ac1-registration@example.test';
    RateLimiter::clear('new_reg_otp_send:'.md5('email'.$value));
    RateLimiter::clear('new_reg_otp_cooldown:'.md5('email'.$value));

    $service->sendForNewRegistration('email', $value);
    $refused = function () use ($service, $value): string {
        try {
            $service->sendForNewRegistration('email', $value);
        } catch (ValidationException $e) {
            return $e->errors()['contact_value'][0];
        }
        $this->fail('the cooldown should have refused');
    };

    // It read *Please wait 120 seconds*.
    expect($refused())->toBe('Please wait 2 minutes before requesting a new code.');

    app()->setLocale('dv');
    expect($refused())->toBe(__('account.error_otp_wait', ['wait' => '2 މިނެޓު'], 'dv'))
        ->not->toMatch('/[A-Za-z]{3,}/');
});
