<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Identity\Models\Otp;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/**
 * One code per registration (STATUS §5oc). The owner, 2026-10-03: "its
 * complicated, otp requires 2 times". A family proved their phone with a
 * code, filled in the details, and was then sent a second code only to
 * accept the terms. Now the terms are accepted on the details form, and a
 * session that proved the contact (or the password) in the last half hour
 * goes straight on. A session signed in longer ago is still asked one code.
 */
function oneCodeUser(): array
{
    $user = User::factory()->create(['force_password_change' => false, 'password' => Hash::make('secret-pass-1')]);
    $contact = UserContact::query()->create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '+9607'.random_int(100000, 999999), 'is_primary' => true, 'verified_at' => now()]);

    return [$user, $contact];
}

function oneCodeCourse(): Course
{
    return Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);
}

function oneCodeDetails(Course $course, array $extra = []): array
{
    return array_merge([
        'flow' => 'adult',
        'course_ids' => [$course->id],
        'first_name' => 'Aishath',
        'last_name' => 'Ali',
        'dob' => now()->subYears(25)->format('Y-m-d'),
        'gender' => 'female',
        'id_type' => 'national_id',
        'national_id' => 'A'.random_int(100000, 999999),
    ], $extra);
}

beforeEach(fn () => $this->withoutLocalizationMiddleware());

it('asks one code: after the contact is proven, the terms on the form are enough and enrolment goes straight through', function () {
    [$user, $contact] = oneCodeUser();
    $course = oneCodeCourse();

    // The one code: a returning family proves their phone.
    Otp::createForContact($contact, 'verify_contact', '246810');
    $this->withSession(['pending_contact_id' => $contact->id, 'pending_user_id' => $user->id, 'pending_selected_course_ids' => [$course->id]])
        ->post(route('courses.register.verify'), ['code' => '246810'])
        ->assertRedirect(route('courses.register.continue'));

    // The form says what happens next, and carries the terms.
    $this->get(route('courses.register.continue'))->assertOk()
        ->assertSee('Confirm enrollment')->assertSee('data-testid="enroll-terms"', false)
        ->assertDontSee('send one code');

    // Without the terms: told so, nothing written.
    $this->from(route('courses.register.continue'))->post(route('courses.register.enroll'), oneCodeDetails($course))
        ->assertRedirect(route('courses.register.continue'))->assertSessionHasErrors('terms_accepted');
    $this->assertDatabaseMissing('course_enrollments', ['course_id' => $course->id]);

    // With them: enrolled, and no second code was ever created.
    $this->from(route('courses.register.continue'))->post(route('courses.register.enroll'), oneCodeDetails($course, ['terms_accepted' => '1']))
        ->assertRedirect(route('courses.register.complete'));
    $this->assertDatabaseHas('course_enrollments', ['course_id' => $course->id]);
    expect(Otp::query()->where('purpose', 'login')->count())->toBe(0);
});

it('counts the password at checkout as the proof too', function () {
    [$user, $contact] = oneCodeUser();
    $course = oneCodeCourse();
    $course->forceFill(['status' => 'open', 'workflow_status' => 'published'])->save();

    $this->post(route('courses.checkout.login', $course), ['login_contact' => $contact->value, 'password' => 'secret-pass-1'])
        ->assertRedirect(route('courses.register.continue'));

    $this->from(route('courses.register.continue'))->post(route('courses.register.enroll'), oneCodeDetails($course, ['terms_accepted' => '1']))
        ->assertRedirect(route('courses.register.complete'));
    expect(Otp::query()->where('purpose', 'login')->count())->toBe(0);
});

it('still asks one code when the session was signed in a while ago, with the terms already ticked', function () {
    [$user] = oneCodeUser();
    $course = oneCodeCourse();

    // Signed in, but nothing proven in this registration.
    $this->actingAs($user)->withSession(['pending_selected_course_ids' => [$course->id]])
        ->get(route('courses.register.continue'))->assertOk()->assertSee('send one code to your phone');
    $this->actingAs($user)->post(route('courses.register.enroll'), oneCodeDetails($course, ['terms_accepted' => '1']))
        ->assertRedirect(route('courses.register.enroll.otp'));
    $this->actingAs($user)->get(route('courses.register.enroll.otp'))->assertOk()
        ->assertSee('id="terms-check" type="checkbox" value="1" checked', false);

    // A proof older than the window, or for somebody else, does not count either.
    $this->actingAs($user)->withSession(['registration_proven' => ['user_id' => $user->id, 'at' => now()->subMinutes(31)->getTimestamp()]])
        ->post(route('courses.register.enroll'), oneCodeDetails($course, ['terms_accepted' => '1']))
        ->assertRedirect(route('courses.register.enroll.otp'));
    $this->actingAs($user)->withSession(['registration_proven' => ['user_id' => $user->id + 999, 'at' => now()->getTimestamp()]])
        ->post(route('courses.register.enroll'), oneCodeDetails($course, ['terms_accepted' => '1']))
        ->assertRedirect(route('courses.register.enroll.otp'));
    $this->assertDatabaseMissing('course_enrollments', ['course_id' => $course->id]);
});
