<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * The course registration's first steps in Dhivehi and Arabic (BACKLOG C21,
 * slice RG1a, STATUS §5qz).
 *
 * The checkout page every course links to, the page a closed course shows and
 * the code page were English throughout, and so was what the server said on
 * them: an ID number missing, an adult under eighteen, a code sent, a wrong
 * password, a session ended. Most of the checkout's fields had no name. And a
 * closed course could be enrolled in: its checkout address showed the old
 * contact form, and nothing after it asked whether enrolment was open.
 */
uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutLocalizationMiddleware());

function rg1aViews(): array
{
    return ['courses/checkout.blade.php', 'courses/register.blade.php', 'courses/register-otp.blade.php'];
}

function rg1aBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/register.php");
}

function rg1aCourse(array $overrides = []): Course
{
    return Course::factory()->create(array_merge(['title' => 'RG1a Tajweed', 'registration_fee_amount' => 0, 'requires_admin_approval' => false], $overrides));
}

function rg1aNewRegistration(Course $course, array $overrides = []): array
{
    return array_merge([
        'course_id' => $course->id, 'flow_type' => 'adult', 'contact_type' => 'mobile', 'contact_value' => '7'.random_int(100000, 999999),
        'first_name' => 'Aishath', 'last_name' => 'Ali', 'gender' => 'female', 'dob' => now()->subYears(25)->format('Y-m-d'),
        'id_type' => 'national_id', 'national_id' => 'A'.random_int(100000, 999999), 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
    ], $overrides);
}

it('prints no English of its own on the checkout, the closed page and the code page', function () {
    $found = [];
    foreach (rg1aViews() as $view) {
        $path = resource_path('views/'.$view);
        // An address and a number are typed the same in every language.
        foreach ([...bladeBareEnglish($path, ['you@example.com']), ...bladeEnglishLiterals($path)] as $text) {
            $found[] = "{$view}: {$text}";
        }
    }

    expect($found)->toBe([]);
});

it('keys every phrase the pages and the server name in three languages', function () {
    [$en, $dv, $ar] = [rg1aBook('en'), rg1aBook('dv'), rg1aBook('ar')];
    $sources = [
        ...array_map(fn (string $view) => resource_path('views/'.$view), rg1aViews()),
        base_path('app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php'),
        base_path('app/Http/Requests/Registration/StartRegistrationRequest.php'),
    ];
    $keys = [];
    foreach ($sources as $source) {
        preg_match_all("/'register\\.([a-z_]+)'/", (string) file_get_contents($source), $found);
        $keys = [...$keys, ...$found[1]];
    }
    $keys = array_values(array_unique($keys));
    expect($keys)->toContain('title', 'closed_title', 'code_heading', 'error_wrong_login', 'error_closed_named', 'error_phone');

    foreach ($keys as $key) {
        expect(array_key_exists($key, $en))->toBeTrue("register.{$key} has no English")
            ->and($dv[$key] ?? '')->toMatch('/\p{Thaana}/u', "register.{$key} in Dhivehi")
            ->and($ar[$key] ?? '')->toMatch('/\p{Arabic}/u', "register.{$key} in Arabic");
    }
    // The three books say the same phrases.
    expect(array_keys($dv))->toEqualCanonicalizing(array_keys($en))
        ->and(array_keys($ar))->toEqualCanonicalizing(array_keys($en));
});

it('leaves none of the first steps\' English on the server', function () {
    expect(refusalEnglishIn('app/Http/Requests/Registration/StartRegistrationRequest.php'))->toBe([]);

    $controller = (string) file_get_contents(base_path('app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php'));
    foreach ([
        'Too many login attempts', 'Incorrect contact or password', 'Please enter your Maldivian ID card number.',
        'Please enter your passport number.', 'If enrolling a child, choose', 'Verification code sent',
        'A new verification code has been sent.',
    ] as $english) {
        expect($controller)->not->toContain($english);
    }
});

it('names every field of the checkout, and reads the code-login box for an email', function () {
    $checkout = (string) file_get_contents(resource_path('views/courses/checkout.blade.php'));

    expect(unnamedFields($checkout))->toBe([])
        // The type of contact follows the code-login box, not the first contact box on the page.
        ->and($checkout)->toContain("getElementById('otp_contact_value')")
        ->and($checkout)->not->toContain("querySelector('[name=\"contact_value\"]')");
});

it('serves the checkout in Dhivehi, and the page of a closed course with no form', function () {
    $dv = rg1aBook('dv');
    app()->setLocale('dv');

    $open = rg1aCourse(['registration_fee_amount' => 500]);
    $this->get(route('courses.checkout.show', $open))->assertOk()
        ->assertSee(str_replace(':course', $open->title, $dv['title']))
        ->assertSee($dv['tab_new'])->assertSee($dv['send_code'])
        ->assertSee(str_replace(':amount', str_replace(':amount', '500.00', $dv['money']), $dv['fee']));

    $closed = rg1aCourse(['status' => 'closed', 'title' => 'RG1a Closed']);
    $this->get(route('courses.checkout.show', $closed))->assertOk()
        ->assertSee($dv['closed_title'])->assertSee('data-testid="enrolment-closed"', false)
        ->assertDontSee('name="contact_value"', false);

    $past = rg1aCourse(['enrollment_deadline' => now()->subDay()->toDateString(), 'title' => 'RG1a Past']);
    $this->get(route('courses.checkout.show', $past))->assertOk()->assertSee($dv['closed_title']);
});

it('starts nothing, logs in to nothing and enrols in nothing for a closed course', function () {
    $dv = rg1aBook('dv');
    app()->setLocale('dv');
    $closed = rg1aCourse(['status' => 'closed', 'title' => 'RG1a Closed']);

    $this->post(route('courses.register.start'), rg1aNewRegistration($closed))
        ->assertRedirect(route('courses.checkout.show', $closed));
    expect(session('reg_pending_data'))->toBeNull();

    $user = User::factory()->create(['password' => Hash::make('secret-pass-1'), 'force_password_change' => false]);
    $contact = UserContact::query()->create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '+9607'.random_int(100000, 999999), 'is_primary' => true, 'verified_at' => now()]);
    $this->post(route('courses.checkout.login', $closed), ['login_contact' => $contact->value, 'password' => 'secret-pass-1'])
        ->assertRedirect(route('courses.checkout.show', $closed));
    $this->assertGuest();

    // Posted by hand, or closed since the form opened: refused, named, and nothing written.
    $this->actingAs($user)->from(route('courses.register.continue'))->post(route('courses.register.enroll'), [
        'flow' => 'adult', 'course_ids' => [$closed->id], 'terms_accepted' => '1',
        'first_name' => 'Aishath', 'last_name' => 'Ali', 'dob' => now()->subYears(25)->format('Y-m-d'), 'gender' => 'female',
        'id_type' => 'national_id', 'national_id' => 'A'.random_int(100000, 999999),
    ])->assertRedirect(route('courses.register.continue'))
        ->assertSessionHasErrors(['course_ids' => str_replace(':course', 'RG1a Closed', $dv['error_closed_named'])]);
    $this->assertDatabaseMissing('course_enrollments', ['course_id' => $closed->id]);
});

it('says what the first steps refuse and what they did, in Dhivehi', function () {
    $dv = rg1aBook('dv');
    app()->setLocale('dv');
    $course = rg1aCourse();
    $from = fn () => $this->from(route('courses.checkout.show', $course));

    $from()->post(route('courses.register.start'), rg1aNewRegistration($course, ['national_id' => '']))
        ->assertSessionHasErrors(['national_id' => $dv['error_id_card']]);
    $from()->post(route('courses.register.start'), rg1aNewRegistration($course, ['id_type' => 'passport', 'passport' => '']))
        ->assertSessionHasErrors(['passport' => $dv['error_passport']]);
    $from()->post(route('courses.register.start'), rg1aNewRegistration($course, ['dob' => now()->subYears(15)->format('Y-m-d')]))
        ->assertSessionHasErrors(['dob' => $dv['error_adult_or_parent']]);
    $from()->post(route('courses.register.start'), rg1aNewRegistration($course, ['contact_value' => '12']))
        ->assertSessionHasErrors(['contact_value' => $dv['error_phone']]);
    $from()->post(route('courses.register.start'), rg1aNewRegistration($course, ['contact_type' => 'email', 'contact_value' => 'not-an-address']))
        ->assertSessionHasErrors(['contact_value' => $dv['error_email']]);

    // A new registration: the code is sent, said in Dhivehi, and the code page reads in Dhivehi.
    $from()->post(route('courses.register.start'), rg1aNewRegistration($course))
        ->assertRedirect(route('courses.register.otp'))->assertSessionHas('success', $dv['flash_code_sent_phone']);
    $this->get(route('courses.register.otp'))->assertOk()
        ->assertSee($dv['code_heading'])->assertSee($dv['code_sent_phone'])->assertSee($dv['resend']);
    // A new code once the minute's wait is over.
    $this->travel(2)->minutes();
    $this->post(route('courses.register.otp.resend-new'))->assertSessionHas('success', $dv['flash_code_resent']);

    // A wrong password at the checkout, said without saying whether the account exists.
    $from()->post(route('courses.checkout.login', $course), ['login_contact' => '7000000', 'password' => 'wrong-one'])
        ->assertSessionHasErrors(['login_contact' => $dv['error_wrong_login']]);

    // A session that has ended.
    $this->flushSession();
    $this->get(route('courses.register.otp'))->assertRedirect(route('public.courses.index'))
        ->assertSessionHas('error', $dv['error_session_restart']);
});

it('says the wait after too many logins in each language, the minutes counted', function () {
    expect(trans_choice('register.error_too_many_logins', 1, ['minutes' => 1], 'en'))->toContain('a minute')
        ->and(trans_choice('register.error_too_many_logins', 15, ['minutes' => 15], 'en'))->toContain('15 minutes')
        ->and(trans_choice('register.error_too_many_logins', 15, ['minutes' => 15], 'dv'))->toContain('15 މިނެޓު')
        ->and(trans_choice('register.error_too_many_logins', 2, ['minutes' => 2], 'ar'))->toContain('دقيقتين')
        ->and(trans_choice('register.error_too_many_logins', 5, ['minutes' => 5], 'ar'))->toContain('5 دقائق')
        ->and(trans_choice('register.error_too_many_logins', 15, ['minutes' => 15], 'ar'))->toContain('15 دقيقة');
});
