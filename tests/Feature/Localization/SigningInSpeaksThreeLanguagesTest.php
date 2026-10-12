<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Identity\Actions\DeleteUserAccountAction;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\People\Models\ParentGuardian;
use App\Domains\People\Models\Student;
use App\Http\Middleware\EnsureVerifiedContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Signing in and resetting a password in Dhivehi and Arabic (BACKLOG C21,
 * slice SI1, STATUS §5rd).
 *
 * The sign-in pages, their layout, the admins' sign-in by code and both
 * password reset flows were English throughout, and so was what the server
 * said on them: a wrong password and too many tries (Laravel's own lines,
 * which it ships in English alone), an inactive account, a code sent, a
 * session ended, an account not found, a password reset. The views called
 * `__('English sentence')` with no such phrase in any book, so the English
 * came back in every language, and the source rule (which skips `{{ }}`) could
 * not see it.
 *
 * Reading them found four things the pages said wrong or nowhere:
 * - the two reset pages on the public layout said a code sent twice, once in
 *   the layout and once on the page;
 * - the admins' sign-in by code said nowhere that a code was refused (too
 *   many, too soon, not sent): the code service says it on `contact`, and
 *   the pages showed `identifier` and `code` only;
 * - the checkout's "use a code instead" sent an account with no password of
 *   its own (made by a code) back to log in with a password it did not have;
 * - every sign-in page's eye button, field and arrow assumed left to right.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['identity.verification.enforce' => false]);
    $this->withoutLocalizationMiddleware();
});

function si1Views(): array
{
    return [
        'layouts/guest.blade.php',
        'auth/login.blade.php',
        'auth/otp-login.blade.php',
        'auth/otp-verify.blade.php',
        'auth/phone-sign-in.blade.php',
        'auth/forgot-password.blade.php',
        'auth/verify-reset-otp.blade.php',
        'auth/reset-password.blade.php',
        'auth/confirm-password.blade.php',
        'auth/verify-email.blade.php',
        'auth/register.blade.php',
        'auth/passwords/otp-request.blade.php',
        'auth/passwords/otp-verify.blade.php',
        'auth/passwords/otp-reset.blade.php',
    ];
}

function si1ServerFiles(): array
{
    return [
        'app/Domains/Identity/Http/Controllers/Auth/OtpLoginController.php',
        'app/Domains/Identity/Http/Controllers/Auth/OtpPasswordResetController.php',
        'app/Domains/Identity/Http/Controllers/Auth/PasswordOtpController.php',
        'app/Http/Requests/Auth/LoginRequest.php',
        'app/Http/Middleware/EnsureVerifiedContact.php',
        'app/Domains/Identity/Http/Controllers/ProfileController.php',
        'app/Domains/Identity/Actions/DeleteUserAccountAction.php',
    ];
}

function si1Book(string $locale, string $book = 'signin'): array
{
    return require base_path("resources/lang/{$locale}/{$book}.php");
}

function si1Sms(): object
{
    $fake = new class implements SmsSenderInterface
    {
        /** @var list<array{0: string, 1: string}> */
        public array $codes = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            return ['success' => true];
        }

        public function sendOtp(string $phoneNumber, string $otp, int $minutes = 10): array
        {
            $this->codes[] = [$phoneNumber, $otp];

            return ['success' => true];
        }

        public function codeFor(string $phoneNumber): ?string
        {
            foreach (array_reverse($this->codes) as [$to, $code]) {
                if ($to === $phoneNumber) {
                    return $code;
                }
            }

            return null;
        }
    };
    app()->instance(SmsSenderInterface::class, $fake);

    return $fake;
}

/** An account with a verified mobile and the password `old-password`. */
function si1Account(string $mobile, array $attributes = []): User
{
    $user = User::factory()->create($attributes + ['password' => Hash::make('old-password'), 'force_password_change' => false]);
    UserContact::query()->create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '+960'.$mobile, 'is_primary' => true, 'verified_at' => now()]);

    return $user;
}

it('prints no English of its own on the sign-in pages and their layout', function () {
    $found = [];
    foreach (si1Views() as $view) {
        $path = resource_path('views/'.$view);
        // The institute's name is a name: the page title's fallback is the app's name.
        foreach ([...bladeBareEnglish($path, ['Akuru Institute']), ...array_diff(bladeEnglishLiterals($path), ['Akuru Institute'])] as $text) {
            $found[] = "{$view}: {$text}";
        }
        // `__('English sentence')` with no such phrase in any book is English in every language.
        preg_match_all("/__\\('([A-Z][^']*\\s[^']*)'\\)/", (string) file_get_contents($path), $sentences);
        foreach ($sentences[1] as $sentence) {
            if (! str_starts_with($sentence, 'After you sign in')) {
                $found[] = "{$view}: __('{$sentence}')";
            }
        }
    }

    expect($found)->toBe([]);
});

it('keys every phrase the pages and the server files name in three languages', function () {
    $keys = [];
    foreach ([...array_map(fn (string $view) => resource_path('views/'.$view), si1Views()), ...array_map('base_path', si1ServerFiles())] as $source) {
        preg_match_all("/'(signin|account)\\.([a-z_0-9]+)'/", (string) file_get_contents($source), $found, PREG_SET_ORDER);
        foreach ($found as [, $book, $key]) {
            $keys["{$book}.{$key}"] = [$book, $key];
        }
    }
    expect(array_keys($keys))->toContain('signin.title', 'signin.code_sent_to', 'signin.forgot_intro', 'signin.flash_password_reset', 'signin.error_inactive', 'account.error_delete_own');

    foreach ($keys as [$book, $key]) {
        expect(array_key_exists($key, si1Book('en', $book)))->toBeTrue("{$book}.{$key} has no English")
            ->and(si1Book('dv', $book)[$key] ?? '')->toMatch('/\p{Thaana}/u', "{$book}.{$key} in Dhivehi")
            ->and(si1Book('ar', $book)[$key] ?? '')->toMatch('/\p{Arabic}/u', "{$book}.{$key} in Arabic");
    }
    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(si1Book($locale)['code_sent_to'])->toContain(':contact')
            ->and(si1Book($locale)['code_sent_enter'])->toContain(':contact')
            ->and(si1Book($locale)['code_hint'])->toContain(':minutes');
    }
});

it('leaves none of the sign-in English on the server', function () {
    foreach (si1ServerFiles() as $file) {
        expect(refusalEnglishIn($file))->toBe([], $file);
    }
});

it("says Laravel's own sign-in lines in Dhivehi and Arabic", function () {
    $english = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/auth.php');
    foreach (['dv' => '/\p{Thaana}/u', 'ar' => '/\p{Arabic}/u'] as $locale => $script) {
        $book = require base_path("resources/lang/{$locale}/auth.php");
        expect(array_keys($book))->toEqualCanonicalizing(array_keys($english));
        foreach ($english as $key => $line) {
            preg_match_all('/:[a-z_]+/', $line, $placeholders);
            expect($book[$key])->toMatch($script);
            foreach ($placeholders[0] as $placeholder) {
                expect($book[$key])->toContain($placeholder);
            }
        }
        foreach (['identifier', 'contact'] as $field) {
            expect((require base_path("resources/lang/{$locale}/validation.php"))['attributes'][$field] ?? '')->toMatch($script);
        }
    }
});

it('names every field of the sign-in pages, and sets what is typed the same in any language left to right', function () {
    foreach (si1Views() as $view) {
        expect(unnamedFields((string) file_get_contents(resource_path('views/'.$view))))->toBe([], $view);
    }
    $login = (string) file_get_contents(resource_path('views/auth/login.blade.php'));
    expect($login)->toContain('inset-inline-end:.75rem')->toContain('padding-inline-end:2.75rem')
        ->toContain('aria-label="{{ __(\'signin.show_password\') }}"')->not->toContain('right:.75rem');
    foreach (['auth/login.blade.php', 'auth/otp-login.blade.php', 'auth/otp-verify.blade.php', 'auth/forgot-password.blade.php', 'auth/verify-reset-otp.blade.php', 'auth/passwords/otp-request.blade.php', 'auth/passwords/otp-verify.blade.php'] as $view) {
        expect((string) file_get_contents(resource_path('views/'.$view)))->toContain('dir="ltr"');
    }
});

it('serves the sign-in page and its layout in Dhivehi, and refuses in Dhivehi', function () {
    $dv = si1Book('dv');
    app()->setLocale('dv');

    $this->get(route('login'))->assertOk()
        ->assertSee($dv['title'])->assertSee($dv['intro'])->assertSee($dv['identifier'])->assertSee($dv['forgot'])
        ->assertSee($dv['remember'])->assertSee($dv['admin_code'])->assertSee($dv['tagline'])->assertSee($dv['quote'])
        ->assertSee($dv['feature_instructors'])->assertSee(__('site.rights_reserved', [], 'dv'))
        ->assertSee('dir="rtl"', false)->assertDontSee('Remember me');

    // A wrong password, in Laravel's own words, now in Dhivehi.
    si1Account('7100001');
    $this->post(route('login'), ['identifier' => '7100001', 'password' => 'not-it'])
        ->assertSessionHasErrors(['identifier' => __('auth.failed', [], 'dv')]);

    // An inactive account.
    si1Account('7100002', ['is_active' => false]);
    $this->post(route('login'), ['identifier' => '7100002', 'password' => 'old-password'])
        ->assertSessionHasErrors(['identifier' => $dv['error_inactive']]);
    $this->assertGuest();

    // Too many tries.
    foreach (range(1, 5) as $try) {
        $this->post(route('login'), ['identifier' => '7100001', 'password' => 'not-it-'.$try]);
    }
    $this->post(route('login'), ['identifier' => '7100001', 'password' => 'not-it'])->assertSessionHasErrors('identifier');
    expect(session('errors')->first('identifier'))->toContain(explode(':seconds', __('auth.throttle', [], 'dv'))[0]);
});

it("serves the admins' sign-in by code in Dhivehi, and says a refused code where it was not said", function () {
    $dv = si1Book('dv');
    app()->setLocale('dv');
    $sms = si1Sms();

    $this->get(route('otp.login.form'))->assertOk()
        ->assertSee($dv['admin_title'])->assertSee($dv['admin_intro'])->assertSee($dv['email_or_phone'])
        ->assertSee($dv['maldives_hint'])->assertSee($dv['send_code'])->assertSee($dv['back_to_password']);

    $this->post(route('otp.request'), ['identifier' => '7100009'])->assertSessionHasErrors(['identifier' => $dv['error_no_account']]);
    si1Account('7100003');
    $this->post(route('otp.request'), ['identifier' => '7100003'])->assertSessionHasErrors(['identifier' => $dv['error_admin_only']]);

    Role::findOrCreate('admin', 'web');
    $admin = si1Account('7100004');
    $admin->assignRole('admin');
    $this->post(route('otp.request'), ['identifier' => '7100004'])
        ->assertRedirect(route('otp.verify.form'))->assertSessionHas('success', $dv['flash_code_sent_phone']);
    [$before] = explode(':contact', $dv['code_sent_to']);
    $this->get(route('otp.verify.form'))->assertOk()
        ->assertSee($dv['code_title'])->assertSee(trim($before))->assertSee('<strong style="color:#374151" dir="ltr">7100004</strong>', false)
        ->assertSee($dv['code_label'])->assertSee(__('signin.code_hint', ['minutes' => 15], 'dv'))->assertSee($dv['verify_sign_in'])
        ->assertSee($dv['other_account'])->assertDontSee(':contact');

    // At once a new code is refused (the minute's wait), on `contact` — said on the page now.
    $this->post(route('otp.resend'))->assertSessionHasErrors('contact');
    $this->get(route('otp.verify.form'))->assertOk()->assertSee('data-testid="otp-resend-refused"', false);

    // The code signs the admin in.
    $this->post(route('otp.verify'), ['code' => $sms->codeFor('+9607100004')]);
    $this->assertAuthenticatedAs($admin);
});

it('says an ended session and a missing account in Dhivehi on the code sign-in', function () {
    $dv = si1Book('dv');
    app()->setLocale('dv');

    $this->get(route('otp.verify.form'))->assertRedirect(route('otp.login.form'))->assertSessionHasErrors(['identifier' => $dv['error_identifier_first']]);
    $this->post(route('otp.verify'), ['code' => '123456'])->assertSessionHasErrors(['identifier' => $dv['error_session_expired']]);
    $this->post(route('otp.resend'))->assertSessionHasErrors(['identifier' => $dv['error_session_expired']]);
    $this->withSession(['otp_login_contact_id' => 999999])->post(route('otp.verify'), ['code' => '123456'])
        ->assertSessionHasErrors(['identifier' => $dv['error_account_not_found']]);
});

it('resets a password on the forgot-password pages in Dhivehi', function () {
    $dv = si1Book('dv');
    app()->setLocale('dv');
    $sms = si1Sms();

    $this->get(route('password.request'))->assertOk()
        ->assertSee($dv['forgot_intro'])->assertSee($dv['forgot_students'])->assertSee($dv['forgot_identifier'])->assertSee($dv['send_verification_code']);

    // Nobody's ID card number: the same words as anybody's.
    $this->post(route('password.email'), ['identifier' => 'A999990'])->assertSessionHas('status', $dv['flash_code_if_registered']);
    $this->get(route('password.reset.verify'))->assertOk()->assertSee($dv['verify_intro'])->assertSee($dv['verification_code'])->assertSee($dv['flash_code_if_registered']);
    $this->post(route('password.reset.verify.store'), ['code' => '123456'])->assertSessionHasErrors(['code' => __('account.error_otp_invalid_or_expired', [], 'dv')]);
    $this->post(route('password.store'), ['password' => 'Brand-new-pass-2026', 'password_confirmation' => 'Brand-new-pass-2026'])
        ->assertRedirect(route('password.request'))->assertSessionHasErrors(['identifier' => $dv['error_session_expired']]);

    // A child's ID card number: the code goes to the parent's phone, said in the same words as any number's (the page tells students where it goes).
    $parent = si1Account('7100005');
    $child = User::factory()->create(['national_id' => 'A910005', 'password' => Hash::make('old-child')]);
    $student = Student::query()->create(['user_id' => $child->id, 'first_name' => 'Aisha', 'last_name' => 'Ali', 'date_of_birth' => '2014-03-01', 'gender' => 'female']);
    $guardian = ParentGuardian::query()->create(['user_id' => $parent->id, 'first_name' => 'Hassan', 'last_name' => 'Ali', 'phone' => '7100005', 'email' => 'si1-parent@example.com', 'address' => 'Malé', 'relationship' => 'father']);
    $student->guardians()->attach($guardian->id, ['relationship' => 'father']);
    $this->post(route('password.email'), ['identifier' => 'A910005'])->assertSessionHas('status', $dv['flash_code_if_registered']);
    $this->post(route('password.reset.verify.store'), ['code' => $sms->codeFor('+9607100005')])->assertRedirect(route('password.reset'));
    $this->get(route('password.reset'))->assertOk()->assertSee($dv['new_password'])->assertSee($dv['confirm_password'])->assertSee($dv['reset_password']);
    $this->post(route('password.store'), ['password' => 'Brand-new-pass-2026', 'password_confirmation' => 'Brand-new-pass-2026'])
        ->assertRedirect(route('login'))->assertSessionHas('status', $dv['flash_password_reset']);
    $this->get(route('login'))->assertSee($dv['flash_password_reset']);
    expect(Hash::check('Brand-new-pass-2026', $child->fresh()->password))->toBeTrue();
});

it('resets a password on the pages the sign-in links to in Dhivehi, a code sent said once', function () {
    $dv = si1Book('dv');
    app()->setLocale('dv');
    $sms = si1Sms();
    $user = si1Account('7100006');

    // A child signs in with an ID card number, which only the forgot-password page takes: it is linked now.
    $this->get(route('password.otp.request'))->assertOk()
        ->assertSee($dv['reset_title'])->assertSee($dv['reset_intro'])->assertSee($dv['mobile_or_email'])->assertSee($dv['back_to_sign_in'])
        ->assertSee($dv['reset_with_id_card'])->assertSee('href="'.route('password.request').'"', false);

    $this->post(route('password.otp.send'), ['contact' => '7100006'])
        ->assertRedirect(route('password.otp.verify.form'))->assertSessionHas('success', $dv['flash_code_if_registered']);
    [$before] = explode(':contact', $dv['code_sent_enter']);
    $page = $this->get(route('password.otp.verify.form'))->assertOk()
        ->assertSee($dv['enter_code_title'])->assertSee(trim($before))->assertSee('<strong dir="ltr">+9607100006</strong>', false)
        ->assertSee($dv['verify_code'])->assertSee($dv['other_number'])->getContent();
    // The layout says it; the page said it again.
    expect(substr_count($page, e($dv['flash_code_if_registered'])))->toBe(1);

    $this->post(route('password.otp.verify'), ['code' => $sms->codeFor('+9607100006')])
        ->assertRedirect(route('password.otp.reset.form'))->assertSessionHas('success', $dv['flash_code_verified']);
    $this->get(route('password.otp.reset.form'))->assertOk()
        ->assertSee($dv['set_new_password'])->assertSee($dv['choose_strong'])->assertSee($dv['at_least_8']);
    $this->post(route('password.otp.update'), ['password' => 'Brand-new-pass-2026', 'password_confirmation' => 'Brand-new-pass-2026'])
        ->assertRedirect(route('login'))->assertSessionHas('status', $dv['flash_password_reset']);
    expect(Hash::check('Brand-new-pass-2026', $user->fresh()->password))->toBeTrue();

    // Without a reset in progress.
    $this->post(route('password.otp.verify'), ['code' => '123456'])->assertSessionHasErrors(['contact' => $dv['error_session_expired']]);
});

it('says the verified-contact gate, and an account not to be removed, in Dhivehi', function () {
    $dv = si1Book('dv');
    $account = si1Book('dv', 'account');
    app()->setLocale('dv');

    $gate = new EnsureVerifiedContact;
    $gate->handle(Request::create('/x'), fn () => response('passed'));
    expect(session('error'))->toBe($dv['error_sign_in_first']);

    $unverified = User::factory()->create();
    $request = Request::create('/x');
    $request->setUserResolver(fn () => $unverified);
    $gate->handle($request, fn () => response('passed'));
    expect(session('error'))->toBe($dv['error_verify_contact']);

    $self = User::factory()->create();
    expect(fn () => app(DeleteUserAccountAction::class)->execute($self, $self->id))
        ->toThrow(ValidationException::class, $account['error_delete_own']);
    Role::findOrCreate('super_admin', 'web');
    $super = User::factory()->create();
    $super->assignRole('super_admin');
    expect(fn () => app(DeleteUserAccountAction::class)->execute($super, $self->id))
        ->toThrow(ValidationException::class, $account['error_delete_super_admin']);
    $this->actingAs($super)->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHas('error', $account['error_delete_super_admin_self']);
});

it("sends a code to an account made by a code from the checkout's code box", function () {
    app()->setLocale('dv');
    $sms = si1Sms();
    $course = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);

    // Made by a code, with no password of its own yet: a code, then the password page.
    $codeOnly = si1Account('7100007', ['force_password_change' => true]);
    $this->post(route('courses.register.start'), ['contact_type' => 'mobile', 'contact_value' => '7100007', 'course_id' => $course->id])
        ->assertRedirect(route('courses.register.otp'));
    expect($sms->codeFor('+9607100007'))->not->toBeNull();
    $this->post(route('courses.register.verify'), ['code' => $sms->codeFor('+9607100007')])
        ->assertRedirect(route('courses.register.set-password'));
    expect((int) session('otp_verified_user_id'))->toBe((int) $codeOnly->id);

    // An account with a password still signs in with it; a code is for "Forgot your password?".
    session()->flush();
    si1Account('7100008');
    $this->post(route('courses.register.start'), ['contact_type' => 'mobile', 'contact_value' => '7100008', 'course_id' => $course->id])
        ->assertRedirect(route('courses.checkout.show', $course))->assertSessionHas('existing_account', '7100008');

    // And the new registration's form, its number taken, goes back as before.
    $this->post(route('courses.register.start'), ['contact_type' => 'mobile', 'contact_value' => '7100007', 'course_id' => $course->id, 'first_name' => 'Aisha'])
        ->assertRedirect(route('courses.checkout.show', $course))->assertSessionHas('existing_account', '7100007');
});
