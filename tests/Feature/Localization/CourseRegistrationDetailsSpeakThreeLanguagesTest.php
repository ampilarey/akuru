<?php

use App\Domains\Admissions\Http\Controllers\CourseRegistrationController;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Services\LogSmsSender;
use App\Domains\People\Models\ParentGuardian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The course registration's details form and password page in Dhivehi and
 * Arabic (BACKLOG C21, slice RG1b, STATUS §5ra).
 *
 * The form that asks who is enrolling and their details, with the terms, and
 * the page where an account made for a contact gets its details and first
 * password, were English throughout, and so was what the server said on them:
 * a session ended, the terms not accepted, an adult under eighteen, an ID card
 * already another learner's, a course already enrolled in and its state, the
 * resume link sent or used. Most of their fields had no name. And a refusal on
 * the password page came after the account was claimed: the claim spends the
 * code, so a mistyped year of birth could not be corrected.
 */
uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutLocalizationMiddleware());

function rg1bViews(): array
{
    return ['courses/register-continue.blade.php', 'courses/register-set-password.blade.php'];
}

function rg1bBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/register.php");
}

function rg1bCourse(array $overrides = []): Course
{
    return Course::factory()->create(array_merge(['title' => 'RG1b Tajweed', 'registration_fee_amount' => 0, 'requires_admin_approval' => false], $overrides));
}

/** @return array{0: User, 1: UserContact} */
function rg1bUser(array $overrides = []): array
{
    $user = User::factory()->create(array_merge(['name' => 'Aishath Ali', 'password' => Hash::make('secret-pass-1'), 'force_password_change' => false], $overrides));
    $contact = UserContact::query()->create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '+9607'.random_int(100000, 999999), 'is_primary' => true, 'verified_at' => now()]);

    return [$user, $contact];
}

/** The session of someone who proved their contact a moment ago, with a course chosen. */
function rg1bProven(User $user, Course $course): array
{
    return ['pending_selected_course_ids' => [$course->id], 'registration_proven' => ['user_id' => $user->id, 'at' => now()->getTimestamp()]];
}

function rg1bDetails(Course $course, array $overrides = []): array
{
    return array_merge([
        'flow' => 'adult', 'course_ids' => [$course->id], 'terms_accepted' => '1',
        'first_name' => 'Aishath', 'last_name' => 'Ali', 'dob' => now()->subYears(25)->format('Y-m-d'), 'gender' => 'female',
        'id_type' => 'national_id', 'national_id' => 'A'.random_int(100000, 999999),
    ], $overrides);
}

function rg1bProfile(array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Mariyam', 'last_name' => 'Hassan', 'gender' => 'female', 'dob' => now()->subYears(30)->format('Y-m-d'),
        'id_type' => 'national_id', 'national_id' => 'A'.random_int(100000, 999999),
        'password' => 'chosen-pass-1', 'password_confirmation' => 'chosen-pass-1',
    ], $overrides);
}

/** A child linked to this account, the link verified by the office (item 13). */
function rg1bChild(User $parent, array $overrides = []): \App\Domains\People\Models\Student
{
    $guardian = ParentGuardian::query()->create(['user_id' => $parent->id, 'first_name' => 'Aishath', 'last_name' => 'Ali', 'phone' => '7820288', 'email' => 'parent@example.com', 'address' => 'Malé', 'relationship' => 'mother']);
    $child = makeCourseStudent(array_merge(['first_name' => 'Mariyam', 'last_name' => 'Ali', 'date_of_birth' => now()->subYears(9)->toDateString()], $overrides));
    $child->guardians()->attach($guardian->id, ['relationship' => 'mother', 'is_primary' => true, 'verification_status' => 'verified']);

    return $child;
}

/** Every string with two English words in the methods behind the two pages — the slice's own, by name. */
function rg1bServerEnglish(): array
{
    $file = file(base_path('app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php'));
    $found = [];
    foreach (['passwordForm', 'setPassword', 'selfEnrolmentRefusal', 'idLabel', 'continueForm', 'enroll', 'humanEnrollmentStatus', 'validateEnrollRequest', 'sendResumeLink', 'resume'] as $method) {
        $reflection = new ReflectionMethod(CourseRegistrationController::class, $method);
        $source = implode('', array_slice($file, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
        foreach (token_get_all('<?php '.$source) as $token) {
            // The resume link's contact ordering is SQL, not a sentence.
            if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
                && preg_match('/[A-Za-z]{2,} [A-Za-z]{2,}/', $token[1]) === 1 && ! str_contains($token[1], 'CASE WHEN')) {
                $found[] = "{$method}: {$token[1]}";
            }
        }
    }

    return $found;
}

it('prints no English of its own on the details form and the password page', function () {
    $found = [];
    foreach (rg1bViews() as $view) {
        $path = resource_path('views/'.$view);
        foreach ([...bladeBareEnglish($path), ...bladeEnglishLiterals($path)] as $text) {
            $found[] = "{$view}: {$text}";
        }
    }

    expect($found)->toBe([]);
});

it('keys every phrase the two pages name in three languages', function () {
    [$en, $dv, $ar] = [rg1bBook('en'), rg1bBook('dv'), rg1bBook('ar')];
    $keys = [];
    foreach (rg1bViews() as $view) {
        preg_match_all("/'register\\.([a-z_]+)'/", (string) file_get_contents(resource_path('views/'.$view)), $found);
        $keys = [...$keys, ...$found[1]];
    }
    $keys = array_values(array_unique($keys));
    expect($keys)->toContain('details_title', 'how_parent', 'child_age', 'terms_agree', 'profile_title', 'create_account', 'show_password');

    foreach ($keys as $key) {
        expect(array_key_exists($key, $en))->toBeTrue("register.{$key} has no English")
            ->and($dv[$key] ?? '')->toMatch('/\p{Thaana}/u', "register.{$key} in Dhivehi")
            ->and($ar[$key] ?? '')->toMatch('/\p{Arabic}/u', "register.{$key} in Arabic");
    }
    // The sentence with the two links keeps both, in each language.
    foreach ([$en, $dv, $ar] as $book) {
        expect($book['terms_agree'])->toContain(':terms')->toContain(':refunds');
    }
});

it('leaves none of the two pages\' English on the server', function () {
    expect(rg1bServerEnglish())->toBe([]);
});

it('names every field of the two pages, and gives the details form\'s script its old input through @js', function () {
    foreach (rg1bViews() as $view) {
        expect(unnamedFields((string) file_get_contents(resource_path('views/'.$view))))->toBe([], $view);
    }

    $continue = (string) file_get_contents(resource_path('views/courses/register-continue.blade.php'));
    expect($continue)->toContain('flow: @js($restoredFlow)')->not->toContain("'{{ \$restored");
});

it('serves the details form in Dhivehi, both ways of enrolling, with the terms\' links in the sentence', function () {
    $dv = rg1bBook('dv');
    app()->setLocale('dv');
    [$user] = rg1bUser();
    $course = rg1bCourse(['registration_fee_amount' => 300]);

    $page = $this->actingAs($user)->withSession(rg1bProven($user, $course))->get(route('courses.register.continue'))->assertOk()
        ->assertSee($dv['details_title'])->assertSee($dv['resume_send'])
        ->assertSee($dv['how_parent'])->assertSee($dv['how_adult'])->assertSee($dv['child_password_title'])
        ->assertSee($dv['term_fees'])->assertSee($dv['confirm_enrollment'])->assertSee($dv['pay_next'])
        ->assertSee(str_replace(':amount', '300.00', $dv['money']))
        ->assertSee($dv['terms_link'])->assertSee($dv['refunds_link'])
        ->assertSee('href="'.route('public.page.show', 'terms').'"', false)
        ->assertDontSee(':terms')->assertDontSee(':refunds');
    // The sentence is the book's with the links in their places: what stands between them is printed as written.
    [$before, $middle] = explode(':refunds', explode(':terms', $dv['terms_agree'])[1]);
    expect($page->getContent())->toContain(e($before));
});

it('names a child in the list by their age, counted in each language', function () {
    [$user] = rg1bUser();
    $course = rg1bCourse();
    rg1bChild($user);

    foreach (['en' => 'Mariyam Ali (9 years)', 'dv' => 'Mariyam Ali (9 އަހަރު)', 'ar' => 'Mariyam Ali (9 سنوات)'] as $locale => $named) {
        app()->setLocale($locale);
        $this->actingAs($user)->withSession(rg1bProven($user, $course))->get(route('courses.register.continue'))->assertOk()
            ->assertSee($named)->assertSee(rg1bBook($locale)['child_existing']);
    }
});

it('says what the details form refuses in Dhivehi, naming the course, the state and the choice to make', function () {
    $dv = rg1bBook('dv');
    app()->setLocale('dv');
    [$user] = rg1bUser();
    $course = rg1bCourse(['title' => 'RG1b Seerah']);
    $post = fn (array $data) => $this->actingAs($user)->withSession(rg1bProven($user, $course))
        ->from(route('courses.register.continue'))->post(route('courses.register.enroll'), $data);

    $post(rg1bDetails($course, ['terms_accepted' => null]))
        ->assertSessionHasErrors(['terms_accepted' => $dv['error_terms']]);
    $post(rg1bDetails($course, ['dob' => now()->subYears(15)->format('Y-m-d')]))
        ->assertSessionHasErrors(['dob' => str_replace(':option', $dv['how_parent'], $dv['error_adult_self'])]);

    // Their own record, already enrolled and confirmed.
    $own = makeCourseStudent(['user_id' => $user->id, 'first_name' => 'Aishath', 'last_name' => 'Ali']);
    CourseEnrollment::query()->create(['unified_student_id' => $own->id, 'course_id' => $course->id, 'status' => 'active', 'payment_status' => 'not_required']);
    $post(rg1bDetails($course))->assertSessionHasErrors(['course_ids' => strtr($dv['error_already_enrolled'], [':course' => 'RG1b Seerah', ':status' => $dv['status_active']])]);

    // A child of theirs already enrolled, the payment still to make.
    $child = rg1bChild($user);
    CourseEnrollment::query()->create(['unified_student_id' => $child->id, 'course_id' => $course->id, 'status' => 'pending', 'payment_status' => 'pending']);
    $post(rg1bDetails($course, ['flow' => 'parent', 'student_mode' => 'existing', 'student_id' => $child->id]))
        ->assertSessionHasErrors(['student_id' => strtr($dv['error_child_enrolled'], [':name' => 'Mariyam Ali', ':course' => 'RG1b Seerah', ':status' => $dv['status_payment_pending']])]);

    // A new child's ID card already another learner's: the parent is told the choice by its name on the form.
    [$other] = rg1bUser(['name' => 'Ibrahim Moosa']);
    makeCourseStudent(['user_id' => $other->id, 'first_name' => 'Ibrahim', 'last_name' => 'Moosa', 'national_id' => 'A765432']);
    $post(rg1bDetails($course, ['flow' => 'parent', 'student_mode' => 'new', 'national_id' => 'A765432', 'dob' => now()->subYears(8)->format('Y-m-d'), 'relationship' => 'mother', 'child_password' => 'child-pass-1', 'child_password_confirmation' => 'child-pass-1']))
        ->assertSessionHasErrors(['national_id' => strtr($dv['error_id_your_child'], [':id' => str_replace(':number', 'A765432', $dv['id_card_label']), ':name' => 'Ibrahim Moosa', ':option' => $dv['child_existing']])]);
    $this->assertDatabaseCount('course_enrollments', 2);
});

it('says in Dhivehi when the session behind the two pages has ended', function () {
    $dv = rg1bBook('dv');
    app()->setLocale('dv');

    $this->get(route('courses.register.continue'))->assertRedirect(route('public.courses.index'))->assertSessionHas('error', $dv['error_session']);
    $this->post(route('courses.register.enroll'), [])->assertRedirect(route('public.courses.index'))->assertSessionHas('error', $dv['error_session_restart']);
    $this->get(route('courses.register.set-password'))->assertRedirect(route('public.courses.index'))->assertSessionHas('error', $dv['error_session']);

    // The account is known, but its code was never entered.
    [$user, $contact] = rg1bUser(['force_password_change' => true]);
    $this->withSession(['pending_contact_id' => $contact->id, 'pending_user_id' => $user->id])
        ->get(route('courses.register.set-password'))->assertRedirect(route('courses.register.otp'))->assertSessionHas('error', $dv['error_code_first']);

    // Signed in, with no course chosen.
    $this->actingAs($user)->get(route('courses.register.continue'))->assertRedirect(route('public.courses.index'))->assertSessionHas('error', $dv['error_no_course']);
});

it('serves the password page in Dhivehi, and refuses before the account is claimed, so the form can be sent again', function () {
    $dv = rg1bBook('dv');
    app()->setLocale('dv');
    [$user, $contact] = rg1bUser(['name' => 'User', 'force_password_change' => true, 'password' => Hash::make('nobody-knows-1')]);
    $course = rg1bCourse();
    $session = ['pending_contact_id' => $contact->id, 'pending_user_id' => $user->id, 'otp_verified_user_id' => $user->id, 'pending_selected_course_ids' => [$course->id]];

    $this->withSession($session)->get(route('courses.register.set-password'))->assertOk()
        ->assertSee($dv['profile_title'])->assertSee($dv['step_verify'])->assertSee($dv['step_profile'])
        ->assertSee($dv['create_account'])->assertSee('aria-label="'.e($dv['show_password']).'"', false);

    // A year of birth typed wrong: refused, and nothing claimed.
    $this->withSession($session)->from(route('courses.register.set-password'))
        ->post(route('courses.register.set-password.store'), rg1bProfile(['dob' => now()->subYears(12)->format('Y-m-d')]))
        ->assertRedirect(route('courses.register.set-password'))
        ->assertSessionHasErrors(['dob' => $dv['error_adult_password']]);
    expect($user->fresh()->force_password_change)->toBeTrue()
        ->and(Hash::check('nobody-knows-1', $user->fresh()->password))->toBeTrue()
        ->and(session('otp_verified_user_id'))->toBe($user->id);
    $this->assertGuest();

    // Corrected and sent again: the account is made, and the next step says so in Dhivehi.
    $this->from(route('courses.register.set-password'))
        ->post(route('courses.register.set-password.store'), rg1bProfile())
        ->assertRedirect(route('courses.register.enroll.otp'))
        ->assertSessionHas('success', $dv['flash_account_ready']);
    expect($user->fresh()->force_password_change)->toBeFalse()
        ->and(Hash::check('chosen-pass-1', $user->fresh()->password))->toBeTrue();
});

it('refuses on the password page an ID card that is another learner\'s, before the account is claimed', function () {
    $dv = rg1bBook('dv');
    app()->setLocale('dv');
    [$user, $contact] = rg1bUser(['name' => 'User', 'force_password_change' => true]);
    [$other] = rg1bUser(['name' => 'Ibrahim Moosa']);
    makeCourseStudent(['user_id' => $other->id, 'first_name' => 'Ibrahim', 'last_name' => 'Moosa', 'national_id' => 'A246802']);
    $course = rg1bCourse();

    $this->withSession(['pending_contact_id' => $contact->id, 'pending_user_id' => $user->id, 'otp_verified_user_id' => $user->id, 'pending_selected_course_ids' => [$course->id]])
        ->from(route('courses.register.set-password'))
        ->post(route('courses.register.set-password.store'), rg1bProfile(['national_id' => 'A246802']))
        ->assertSessionHasErrors(['national_id' => strtr($dv['error_id_log_in'], [':id' => str_replace(':number', 'A246802', $dv['id_card_label']), ':name' => 'Ibrahim Moosa'])]);
    expect($user->fresh()->force_password_change)->toBeTrue()
        ->and($user->fresh()->national_id)->not->toBe('A246802');
});

it('sends and opens the resume link in Dhivehi, and the code page says its welcome back once', function () {
    $dv = rg1bBook('dv');
    app()->setLocale('dv');
    app()->instance(SmsSenderInterface::class, new LogSmsSender);
    [$user, $contact] = rg1bUser();
    $course = rg1bCourse();
    RateLimiter::clear('otp:send:'.$contact->id.':verify_contact');
    RateLimiter::clear('otp:cooldown:'.$contact->id.':verify_contact');

    $this->actingAs($user)->withSession(['pending_selected_course_ids' => [$course->id]])->from(route('courses.register.continue'))
        ->post(route('courses.register.resume-link'))
        ->assertRedirect(route('courses.register.continue'))
        ->assertSessionHas('success', str_replace(':contact', $contact->value, $dv['flash_link_sent']));
    $this->actingAs($user)->withSession(['pending_selected_course_ids' => []])->from(route('courses.register.continue'))
        ->post(route('courses.register.resume-link'))->assertSessionHas('error', $dv['error_no_course']);

    $this->get(route('courses.register.resume', ['flow' => 'not-a-flow', 't' => 'nothing']))
        ->assertRedirect(route('public.courses.index'))->assertSessionHas('error', $dv['error_link_used']);

    // A good link: the code page, with the welcome back said on it.
    auth()->logout();
    $this->flushSession();
    $url = app(\App\Domains\Admissions\Actions\IssueRegistrationResumeLinkAction::class)
        ->execute($user->id, $contact->id, 'mobile', $contact->value, ['course_ids' => [$course->id]])['url'];
    $this->get($url)->assertRedirect(route('courses.register.otp'))->assertSessionHas('info', $dv['flash_welcome_back']);
    $code = $this->get(route('courses.register.otp'))->assertOk()->assertSee($dv['flash_welcome_back'])->getContent();
    expect(substr_count($code, e($dv['flash_welcome_back'])))->toBe(1);
});

it('shows a code sent once on the code page, where it was shown twice', function () {
    $dv = rg1bBook('dv');
    app()->setLocale('dv');
    $course = rg1bCourse();

    $this->from(route('courses.checkout.show', $course))->post(route('courses.register.start'), [
        'course_id' => $course->id, 'flow_type' => 'adult', 'contact_type' => 'mobile', 'contact_value' => '7'.random_int(100000, 999999),
        'first_name' => 'Aishath', 'last_name' => 'Ali', 'gender' => 'female', 'dob' => now()->subYears(25)->format('Y-m-d'),
        'id_type' => 'national_id', 'national_id' => 'A'.random_int(100000, 999999), 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
    ])->assertRedirect(route('courses.register.otp'));

    $page = $this->get(route('courses.register.otp'))->assertOk()->getContent();
    expect(substr_count($page, e($dv['flash_code_sent_phone'])))->toBe(1);
});

it('names an enrolment\'s state in each language', function () {
    $status = new ReflectionMethod(CourseRegistrationController::class, 'humanEnrollmentStatus');
    $controller = app(CourseRegistrationController::class);
    $named = function (string $state, string $payment, string $locale) use ($status, $controller): string {
        app()->setLocale($locale);

        return $status->invoke($controller, (new CourseEnrollment)->forceFill(['status' => $state, 'payment_status' => $payment]));
    };

    // Every state the office's enrolment screens name (`admin.enrolments_status_*`); a rejected one is never reported.
    $states = array_map(fn (string $key) => substr($key, strlen('enrolments_status_')), array_values(array_filter(
        array_keys(require base_path('resources/lang/en/admin.php')),
        fn (string $key) => str_starts_with($key, 'enrolments_status_') && $key !== 'enrolments_status_rejected',
    )));
    expect($states)->toContain('active', 'pending', 'approved', 'suspended', 'completed', 'cancelled');

    foreach (['en', 'dv', 'ar'] as $locale) {
        $book = rg1bBook($locale);
        foreach ($states as $state) {
            expect($named($state, 'not_required', $locale))->toBe($book["status_{$state}"], "{$state} in {$locale}");
        }
        expect($named('pending', 'initiated', $locale))->toBe($book['status_payment_pending'])
            ->and($named('pending', 'pending', $locale))->toBe($book['status_payment_pending']);
    }
    // A state with no phrase is printed as stored, not as a missing key.
    expect($named('on_hold', 'not_required', 'dv'))->toBe('on_hold');
});
