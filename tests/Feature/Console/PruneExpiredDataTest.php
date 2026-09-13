<?php

use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\Otp;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * `akuru:prune-expired` is scheduled hourly and **had never run**. No test ever
 * invoked it, and it threw on its first query — `Otp` was filtered on
 * `consumed_at`, a column `user_contact_otps` does not have. Everything after
 * that line was therefore dead too: no OTP was ever pruned, no stale enrolment
 * ever cancelled. A second bug sat behind the first (`payments()` is not a
 * relation on `CourseEnrollment`), which only surfaced once the first was
 * fixed.
 *
 * Both were found by a test written for an unrelated feature that happened to
 * call the command. This file exists so the command is never again shipped
 * unexecuted.
 */
uses(RefreshDatabase::class);

function makeOtp(array $overrides = []): Otp
{
    $user = User::factory()->create();
    $contactId = DB::table('user_contacts')->insertGetId([
        'user_id' => $user->id,
        'type' => 'email',
        'value' => 'otp'.$user->id.'@example.test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Otp::query()->create(array_merge([
        'user_contact_id' => $contactId,
        // `purpose` is a DB enum ('verify_contact','password_reset','login',
        // 'enroll'); anything else is silently truncated by MySQL rather
        // than rejected outright, so it is read off the schema, not guessed.
        'purpose' => 'verify_contact',
        'channel' => 'email',
        'code_hash' => bcrypt('123456'),
        'expires_at' => now()->addMinutes(10),
        'attempts' => 0,
    ], $overrides));
}

it('runs at all', function () {
    // The assertion that would have caught the original bug on day one.
    $this->artisan('akuru:prune-expired')->assertExitCode(0);
});

it('deletes an OTP that was used more than a day ago', function () {
    $otp = makeOtp(['used_at' => now()->subDays(2)]);
    Otp::query()->whereKey($otp->id)->update(['created_at' => now()->subDays(2)]);

    $this->artisan('akuru:prune-expired')->assertExitCode(0);

    expect(Otp::query()->whereKey($otp->id)->exists())->toBeFalse();
});

it('deletes an OTP that expired more than a day ago', function () {
    $otp = makeOtp(['expires_at' => now()->subDays(2)]);
    Otp::query()->whereKey($otp->id)->update(['created_at' => now()->subDays(2)]);

    $this->artisan('akuru:prune-expired')->assertExitCode(0);

    expect(Otp::query()->whereKey($otp->id)->exists())->toBeFalse();
});

it('keeps a live OTP', function () {
    $live = makeOtp();

    $this->artisan('akuru:prune-expired')->assertExitCode(0);

    // A code somebody is about to type must survive the hourly sweep.
    expect(Otp::query()->whereKey($live->id)->exists())->toBeTrue();
});

it('changes nothing on a dry run', function () {
    $otp = makeOtp(['used_at' => now()->subDays(2)]);
    Otp::query()->whereKey($otp->id)->update(['created_at' => now()->subDays(2)]);

    $this->artisan('akuru:prune-expired', ['--dry-run' => true])->assertExitCode(0);

    expect(Otp::query()->whereKey($otp->id)->exists())->toBeTrue();
});

it('never cancels an enrolment whose payment was confirmed', function () {
    $course = \App\Domains\Courses\Models\Course::query()->create([
        'course_category_id' => DB::table('course_categories')->insertGetId([
            'name' => 'Prune test', 'slug' => 'prune-test-'.\Illuminate\Support\Str::random(6), 'order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]),
        'title' => 'Prune course',
        'slug' => 'prune-course-'.\Illuminate\Support\Str::random(6),
        'short_desc' => 'For the prune test.',
        'body' => 'Body.',
        'cover_image' => '',
        'workflow_status' => 'published',
        'course_type' => 'general',
        'status' => 'open',
    ]);

    // `course_enrollments.student_id` FKs to `registration_students`, not
    // `students` — a distinction this repo has tripped over before. Two
    // registrants, because (student, course, term) is unique.
    $paidRegistrant = makeRegistrationStudent()->id;
    $unpaidRegistrant = makeRegistrationStudent()->id;

    $confirmed = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => $paidRegistrant,
        'status' => 'pending',
        'payment_status' => 'confirmed',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);
    CourseEnrollment::query()->whereKey($confirmed->id)->update(['created_at' => now()->subDays(3)]);

    $unpaid = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => $unpaidRegistrant,
        'status' => 'pending',
        'payment_status' => 'pending',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);
    CourseEnrollment::query()->whereKey($unpaid->id)->update(['created_at' => now()->subDays(3)]);

    $this->artisan('akuru:prune-expired')->assertExitCode(0);

    // Rule 12: a confirmed payment is the webhook's word that the school was
    // paid. Cancelling that enrolment takes a paid course away from a student,
    // which is the worst thing this command could possibly do.
    expect($confirmed->fresh()->status)->toBe('pending')
        ->and($unpaid->fresh()->status)->toBe('cancelled');
});

/**
 * `'released'` is the status `RecordDiscountRedemptionAction`'s own docblock
 * describes — *"RELEASED if the payment fails — so usage limits never leak from
 * abandoned checkouts forever"* — and **nothing in the codebase ever wrote it**.
 * `transition()` was only ever called with `'confirmed'`, and there is no
 * payment-failed event at all.
 *
 * `ResolveDiscountAction` counts pending **and** confirmed against both
 * `usage_limit` and `per_user_limit`. So a family who opened checkout and
 * closed the tab burned their only use of the code, and a code limited to 100
 * uses ran out after 100 attempts rather than 100 purchases.
 */
function abandonedCheckoutCourse(): App\Domains\Courses\Models\Course
{
    return App\Domains\Courses\Models\Course::factory()->create([
        'workflow_status' => 'published',
        'status' => 'open',
    ]);
}

function abandonedCheckoutDiscount(): App\Domains\Commerce\Models\DiscountCode
{
    return App\Domains\Commerce\Models\DiscountCode::query()->create([
        'code' => 'WELCOME10',
        'name' => 'Welcome 10%',
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'status' => 'active',
        'per_user_limit' => 1,
        'can_use_with_wallet' => true,
    ]);
}

it('gives a discount slot back when an abandoned checkout is pruned', function () {
    $course = abandonedCheckoutCourse();
    $buyer = App\Domains\Identity\Models\User::factory()->create();
    $discount = abandonedCheckoutDiscount();

    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => makeRegistrationStudent()->id,
        'status' => 'pending',
        'payment_status' => 'pending',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);
    CourseEnrollment::query()->whereKey($enrollment->id)->update(['created_at' => now()->subDays(3)]);

    app(App\Domains\Commerce\Actions\RecordDiscountRedemptionAction::class)
        ->execute($discount->id, $buyer->id, 'course_enrollment', $enrollment->id, 50.0);

    // The code is spent as far as this buyer is concerned, and stays spent
    // forever unless something releases it.
    expect(fn () => app(App\Domains\Commerce\Actions\ResolveDiscountAction::class)
        ->execute('WELCOME10', $buyer->id, 500.0))
        ->toThrow(Illuminate\Validation\ValidationException::class);

    $this->artisan('akuru:prune-expired')->assertExitCode(0);

    expect($enrollment->fresh()->status)->toBe('cancelled');

    $resolved = app(App\Domains\Commerce\Actions\ResolveDiscountAction::class)
        ->execute('WELCOME10', $buyer->id, 500.0);

    expect($resolved['amount_discounted'])->toBe(50.0);
});

it('does not release a redemption whose payment landed', function () {
    // A confirmed redemption means the school was paid. Handing that slot back
    // would let the code be used twice for one purchase.
    $course = abandonedCheckoutCourse();
    $buyer = App\Domains\Identity\Models\User::factory()->create();
    $discount = abandonedCheckoutDiscount();

    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => makeRegistrationStudent()->id,
        'status' => 'pending',
        'payment_status' => 'confirmed',
        'enrollment_type' => 'self_learning',
        'progress_percentage' => 0,
    ]);
    CourseEnrollment::query()->whereKey($enrollment->id)->update(['created_at' => now()->subDays(3)]);

    app(App\Domains\Commerce\Actions\RecordDiscountRedemptionAction::class)
        ->execute($discount->id, $buyer->id, 'course_enrollment', $enrollment->id, 50.0);
    app(App\Domains\Commerce\Actions\RecordDiscountRedemptionAction::class)
        ->transition('course_enrollment', $enrollment->id, 'confirmed');

    $this->artisan('akuru:prune-expired')->assertExitCode(0);

    expect(App\Domains\Commerce\Models\DiscountRedemption::query()
        ->where('purchase_id', $enrollment->id)->value('status'))->toBe('confirmed');
});
