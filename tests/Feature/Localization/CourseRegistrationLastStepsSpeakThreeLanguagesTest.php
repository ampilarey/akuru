<?php

use App\Domains\Admissions\Http\Controllers\CourseRegistrationController;
use App\Domains\Admissions\Services\Enrollment\EnrollmentService;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\Otp;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * The course registration's last steps in Dhivehi and Arabic (BACKLOG C21,
 * slice RG1c, STATUS §5rb).
 *
 * The terms and code step and the last page were English throughout, and so
 * was what the server said on them and on the payment's way out: a code sent
 * or sent again, a session ended, the code's contact gone, already enrolled, a
 * course fully booked, a learner not one's own, the payment gateway out of
 * reach, a payment not found or already confirmed, the course checkout's
 * terms and its free course. And the last page dropped one message and said
 * another twice: a payment already waiting (flashed as info, which nothing
 * printed) and the gateway's failure (printed by the layout and by the page).
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['identity.verification.enforce' => false]);
    $this->withoutLocalizationMiddleware();
});

function rg1cViews(): array
{
    return ['courses/register-enroll-confirm.blade.php', 'courses/register-complete.blade.php'];
}

/** The server files whose refusals reach these pages besides the controller, which RG1a's test reads. */
function rg1cServerFiles(): array
{
    return [
        'app/Domains/Admissions/Http/Controllers/CheckoutController.php',
        'app/Domains/Admissions/Services/Enrollment/EnrollmentService.php',
        'app/Http/Requests/Registration/EnrollAdultRequest.php',
    ];
}

function rg1cBook(string $locale): array
{
    return require base_path("resources/lang/{$locale}/register.php");
}

function rg1cCourse(array $overrides = []): Course
{
    return Course::factory()->create(array_merge(['title' => 'RG1c Seerah', 'registration_fee_amount' => 0, 'requires_admin_approval' => false], $overrides));
}

/** @return array{0: User, 1: UserContact} */
function rg1cUser(): array
{
    $user = User::factory()->create(['name' => 'Aishath Ali', 'force_password_change' => false]);
    $contact = UserContact::query()->create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '+9607'.random_int(100000, 999999), 'is_primary' => true, 'verified_at' => now()]);

    return [$user, $contact];
}

/** The session the details form leaves for the terms and code step: an adult enrolling themselves. */
function rg1cPending(Course $course, UserContact $contact, array $overrides = []): array
{
    return array_merge([
        'enroll_pending_course_ids' => [$course->id],
        'enroll_pending_flow' => 'adult',
        'enroll_pending_data' => ['first_name' => 'Aishath', 'last_name' => 'Ali', 'dob' => now()->subYears(25)->format('Y-m-d'), 'gender' => 'female', 'id_type' => 'national_id', 'national_id' => 'A'.random_int(100000, 999999)],
        'enroll_pending_term_id' => null,
        'enroll_pending_email' => '',
        'enroll_otp_contact_id' => $contact->id,
        'enroll_terms_accepted' => true,
    ], $overrides);
}

/** Every string with two English words in the methods behind the two pages, by name. */
function rg1cServerEnglish(): array
{
    $file = file(base_path('app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php'));
    $found = [];
    foreach (['enrollOtpForm', 'enrollConfirm', 'enrollResendOtp', 'processEnrollmentFromSession', 'complete', 'retryPayment', 'resendEnrollCode'] as $method) {
        $reflection = new ReflectionMethod(CourseRegistrationController::class, $method);
        $source = implode('', array_slice($file, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
        foreach (token_get_all('<?php '.$source) as $token) {
            // The payment start's log line is the operator's, not a page's.
            if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
                && preg_match('/[A-Za-z]{2,} [A-Za-z]{2,}/', $token[1]) === 1 && $token[1] !== "'BML enroll-first initiation'") {
                $found[] = "{$method}: {$token[1]}";
            }
        }
    }

    return $found;
}

it('prints no English of its own on the terms and code step and the last page', function () {
    $found = [];
    foreach (rg1cViews() as $view) {
        $path = resource_path('views/'.$view);
        foreach ([...bladeBareEnglish($path), ...bladeEnglishLiterals($path)] as $text) {
            $found[] = "{$view}: {$text}";
        }
    }

    expect($found)->toBe([]);
});

it('keys every phrase the two pages and the server files name in three languages', function () {
    [$en, $dv, $ar] = [rg1cBook('en'), rg1cBook('dv'), rg1cBook('ar')];
    $keys = [];
    foreach ([...array_map(fn (string $view) => resource_path('views/'.$view), rg1cViews()), ...array_map('base_path', rg1cServerFiles())] as $source) {
        preg_match_all("/'register\\.([a-z_]+)'/", (string) file_get_contents($source), $found);
        $keys = [...$keys, ...$found[1]];
    }
    $keys = array_values(array_unique($keys));
    expect($keys)->toContain('confirm_title', 'consent', 'complete_heading', 'proceed_to_payment', 'error_full', 'error_terms_all', 'error_no_fee');

    foreach ($keys as $key) {
        expect(array_key_exists($key, $en))->toBeTrue("register.{$key} has no English")
            ->and($dv[$key] ?? '')->toMatch('/\p{Thaana}/u', "register.{$key} in Dhivehi")
            ->and($ar[$key] ?? '')->toMatch('/\p{Arabic}/u', "register.{$key} in Arabic");
    }
    foreach ([$en, $dv, $ar] as $book) {
        expect($book['consent'])->toContain(':terms')->toContain(':refunds');
    }
});

it('leaves none of the last steps\' English on the server', function () {
    expect(rg1cServerEnglish())->toBe([]);
    foreach (rg1cServerFiles() as $file) {
        expect(refusalEnglishIn($file))->toBe([], $file);
    }
});

it('names every field of the two pages, and says its own word while the code is sent', function () {
    foreach (rg1cViews() as $view) {
        expect(unnamedFields((string) file_get_contents(resource_path('views/'.$view))))->toBe([], $view);
    }
    expect((string) file_get_contents(resource_path('views/courses/register-enroll-confirm.blade.php')))
        ->toContain("@js(__('register.processing'))")->not->toContain("'Processing…'");
});

it('serves the terms and code step in Dhivehi, before the code and after it', function () {
    $dv = rg1cBook('dv');
    app()->setLocale('dv');
    [$user, $contact] = rg1cUser();
    $course = rg1cCourse();

    $this->actingAs($user)->withSession(rg1cPending($course, $contact))->get(route('courses.register.enroll.otp'))->assertOk()
        ->assertSee($dv['confirm_title'])->assertSee($dv['confirm_read_terms'])->assertSee($dv['summary'])
        ->assertSee($dv['price_free'])->assertSee($dv['term_conduct'])->assertSee($dv['accept_send_code'])
        ->assertSee('id="terms-check" type="checkbox" value="1" checked', false)
        ->assertDontSee(':terms');

    // The code is sent, said once (the layout says it; the page no longer says it too).
    $this->actingAs($user)->post(route('courses.register.enroll.resend'))
        ->assertRedirect(route('courses.register.enroll.otp'))->assertSessionHas('success', $dv['flash_code_sent_phone']);
    $page = $this->actingAs($user)->get(route('courses.register.enroll.otp'))->assertOk()
        ->assertSee($dv['confirm_enter_code'])->assertSee($dv['code_sent_to'])->assertSee($dv['code_lasts'])
        ->assertSee($dv['confirm_enrollment'])->assertSee($dv['terms_link'])->assertSee($dv['resend'])
        ->getContent();
    expect(substr_count($page, e($dv['flash_code_sent_phone'])))->toBe(1);
    [$before] = explode(':terms', $dv['consent']);
    expect($page)->toContain(e(trim($before)));

    // Sent again after the minute's wait.
    $this->travel(2)->minutes();
    $this->actingAs($user)->post(route('courses.register.enroll.resend'))->assertSessionHas('success', $dv['flash_code_resent']);
});

it('says what the code step refuses in Dhivehi', function () {
    $dv = rg1cBook('dv');
    app()->setLocale('dv');
    [$user, $contact] = rg1cUser();
    $course = rg1cCourse();

    // No pending enrolment: the session has ended.
    $this->actingAs($user)->get(route('courses.register.enroll.otp'))->assertRedirect(route('public.courses.index'))
        ->assertSessionHas('error', $dv['error_session_restart']);
    $this->actingAs($user)->post(route('courses.register.enroll.confirm'), ['otp_code' => '123456', 'terms_accepted' => '1'])
        ->assertRedirect(route('public.courses.index'))->assertSessionHas('error', $dv['error_session_restart']);

    // The terms not accepted.
    $this->actingAs($user)->withSession(rg1cPending($course, $contact))->from(route('courses.register.enroll.otp'))
        ->post(route('courses.register.enroll.confirm'), ['otp_code' => '123456'])
        ->assertSessionHasErrors(['terms_accepted' => $dv['error_terms']]);

    // The contact the code was to go to is gone.
    $this->actingAs($user)->withSession(rg1cPending($course, $contact, ['enroll_otp_contact_id' => 999999]))
        ->post(route('courses.register.enroll.confirm'), ['otp_code' => '123456', 'terms_accepted' => '1'])
        ->assertRedirect(route('public.courses.index'))->assertSessionHas('error', $dv['error_contact_missing']);
    $this->actingAs($user)->withSession(rg1cPending($course, $contact, ['enroll_otp_contact_id' => 999999]))
        ->post(route('courses.register.enroll.resend'))
        ->assertRedirect(route('courses.register.continue'))->assertSessionHas('error', $dv['error_contact_missing']);
    $this->flushSession();
    $this->actingAs($user)->post(route('courses.register.enroll.resend'))
        ->assertRedirect(route('courses.register.continue'))->assertSessionHas('error', $dv['error_session_form']);
});

it('finishes a free enrolment on a last page in Dhivehi', function () {
    $dv = rg1cBook('dv');
    app()->setLocale('dv');
    [$user, $contact] = rg1cUser();
    $course = rg1cCourse();
    Otp::createForContact($contact, 'login', '135790');

    $this->actingAs($user)->withSession(rg1cPending($course, $contact))
        ->post(route('courses.register.enroll.confirm'), ['otp_code' => '135790', 'terms_accepted' => '1'])
        ->assertRedirect(route('courses.register.complete'));

    $this->actingAs($user)->get(route('courses.register.complete'))->assertOk()
        ->assertSee($dv['complete_heading'])->assertSee($dv['complete_intro'])->assertSee($dv['next_title'])
        ->assertSee($dv['col_course'])->assertSee($dv['badge_pending'])->assertSee($dv['price_free'])
        ->assertSee($dv['my_enrollments'])->assertSee($dv['browse_courses'])->assertSee('RG1c Seerah');
});

it('says on the last page why a payment is already waiting, and a gateway failure once', function () {
    $dv = rg1cBook('dv');
    app()->setLocale('dv');
    [$user] = rg1cUser();
    $course = rg1cCourse();
    $student = makeCourseStudent(['user_id' => $user->id]);
    $enrollment = CourseEnrollment::query()->create(['unified_student_id' => $student->id, 'course_id' => $course->id, 'status' => 'pending', 'payment_status' => 'pending']);

    $waiting = $this->actingAs($user)->withSession(['enrollments' => [$enrollment->fresh('course')], 'info' => $dv['info_payment_waiting']])
        ->get(route('courses.register.complete'))->assertOk()->getContent();
    expect(substr_count($waiting, e($dv['info_payment_waiting'])))->toBe(1);

    $gateway = __('register.error_gateway', ['button' => $dv['proceed_to_payment']], 'dv');
    $failed = $this->actingAs($user)->withSession(['enrollments' => [$enrollment->fresh('course')], 'error' => $gateway])
        ->get(route('courses.register.complete'))->assertOk()->getContent();
    expect(substr_count($failed, e($gateway)))->toBe(1)
        ->and($failed)->toContain(e($dv['badge_pending']))->toContain(e($dv['payment_due']));

    // Nothing to show: the session has ended.
    $this->flushSession();
    $this->actingAs($user)->get(route('courses.register.complete'))->assertRedirect(route('public.courses.index'))
        ->assertSessionHas('error', $dv['error_no_enrollment']);
});

it('tells someone already enrolled so on their enrolments, in Dhivehi', function () {
    $dv = rg1cBook('dv');
    app()->setLocale('dv');
    [$user, $contact] = rg1cUser();
    $course = rg1cCourse();
    $own = makeCourseStudent(['user_id' => $user->id, 'first_name' => 'Aishath', 'last_name' => 'Ali']);
    CourseEnrollment::query()->create(['unified_student_id' => $own->id, 'course_id' => $course->id, 'status' => 'active', 'payment_status' => 'not_required']);
    Otp::createForContact($contact, 'login', '246802');

    $this->actingAs($user)->withSession(rg1cPending($course, $contact))
        ->post(route('courses.register.enroll.confirm'), ['otp_code' => '246802', 'terms_accepted' => '1'])
        ->assertRedirect(route('my.enrollments'))
        ->assertSessionHas('info', strtr($dv['error_already_enrolled'], [':course' => 'RG1c Seerah', ':status' => $dv['status_active']]));
});

it('says the enrolment\'s own refusals in Dhivehi: a full course, a learner not theirs, an adult too young, a contact not verified', function () {
    $dv = rg1cBook('dv');
    app()->setLocale('dv');
    [$user] = rg1cUser();
    $service = app(EnrollmentService::class);
    $adult = ['first_name' => 'Aishath', 'last_name' => 'Ali', 'dob' => now()->subYears(25)->format('Y-m-d'), 'gender' => 'female', 'id_type' => 'national_id', 'national_id' => 'A'.random_int(100000, 999999)];
    $refusal = function (callable $enrol): array {
        try {
            $enrol();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        return [];
    };

    $full = rg1cCourse(['title' => 'RG1c Full', 'seats' => 1]);
    CourseEnrollment::query()->create(['unified_student_id' => makeCourseStudent()->id, 'course_id' => $full->id, 'status' => 'pending', 'payment_status' => 'not_required']);
    expect($refusal(fn () => $service->enrollAdultSelf($user, $adult, [$full->id])))
        ->toBe(['course' => [str_replace(':course', 'RG1c Full', $dv['error_full'])]]);

    $stranger = makeCourseStudent(['first_name' => 'Ibrahim', 'last_name' => 'Moosa']);
    expect($refusal(fn () => $service->enrollByParent($user, $stranger->id, [rg1cCourse()->id], null)))
        ->toBe(['student' => [$dv['error_not_your_learner']]]);

    expect($refusal(fn () => $service->enrollAdultSelf($user, [...$adult, 'dob' => now()->subYears(12)->format('Y-m-d')], [rg1cCourse()->id])))
        ->toBe(['dob' => [$dv['error_adult_password']]]);

    $unverified = User::factory()->create();
    expect($refusal(fn () => $service->enrollAdultSelf($unverified, $adult, [rg1cCourse()->id])))
        ->toBe(['contact' => [$dv['error_verify_first']]]);
});

it('says the payment\'s refusals in Dhivehi: logged out, not found, the checkout\'s terms and its free course', function () {
    $dv = rg1cBook('dv');
    app()->setLocale('dv');
    [$user] = rg1cUser();

    $this->get(route('courses.register.payment.retry', ['ref' => 'NOPE']))->assertRedirect(route('public.courses.index'))
        ->assertSessionHas('error', $dv['error_login_to_pay']);
    $this->actingAs($user)->get(route('courses.register.payment.retry', ['ref' => 'NOPE']))->assertRedirect(route('public.courses.index'))
        ->assertSessionHas('error', $dv['error_payment_missing']);

    $paid = rg1cCourse(['registration_fee_amount' => 150]);
    $this->actingAs($user)->from(route('checkout.course.show', $paid))->post(route('payments.course.start', $paid), [])
        ->assertSessionHasErrors(['accept_terms' => $dv['error_terms_all']]);
    $free = rg1cCourse();
    $this->actingAs($user)->post(route('payments.course.start', $free), ['accept_terms' => '1'])
        ->assertRedirect(route('courses.register.show', $free))->assertSessionHas('error', $dv['error_no_fee']);
});
