<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Otp;
use App\Models\RegistrationStudent;
use App\Models\User;
use App\Models\UserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CourseRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_registration_creates_user_and_contact(): void
    {
        $course = Course::factory()->create(['registration_fee_amount' => 0]);

        $response = $this->post(route('courses.register.start'), [
            'contact_type' => 'email',
            'contact_value' => 'test@example.com',
            'course_id' => $course->id,
            '_token' => csrf_token(),
        ]);
        $response->assertRedirect();
        $this->assertStringContainsString('courses/register/otp', $response->headers->get('Location') ?? '');

        $contact = UserContact::where('type', 'email')->where('value', 'test@example.com')->first();
        $this->assertNotNull($contact);
        $this->assertNotNull($contact->user);
    }

    public function test_verify_otp_and_enroll_adult_with_zero_fee(): void
    {
        $course = Course::factory()->create([
            'registration_fee_amount' => 0,
            'requires_admin_approval' => false,
        ]);

        $user = User::factory()->create(['force_password_change' => true]);
        $contact = UserContact::create([
            'user_id' => $user->id,
            'type' => 'email',
            'value' => 'test@example.com',
            'is_primary' => true,
            'verified_at' => null,
        ]);

        Otp::createForContact($contact, 'verify_contact', '123456');

        session(['pending_contact_id' => $contact->id, 'pending_user_id' => $user->id, 'pending_course_id' => $course->id]);

        $this->post(route('courses.register.verify'), [
            'code' => '123456',
            '_token' => csrf_token(),
        ])->assertRedirect(route('courses.register.set-password'));

        $contact->refresh();
        $this->assertNotNull($contact->verified_at);

        $this->post(route('courses.register.set-password'), [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            '_token' => csrf_token(),
        ])->assertRedirect(route('courses.register.continue'));

        $this->post(route('courses.register.enroll'), [
            'flow' => 'adult',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dob' => now()->subYears(20)->format('Y-m-d'),
            'course_ids' => [$course->id],
            '_token' => csrf_token(),
        ]);

        $this->assertDatabaseHas('course_enrollments', [
            'course_id' => $course->id,
            'payment_status' => 'not_required',
        ]);
    }

    public function test_existing_user_detection_skips_set_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'force_password_change' => false,
        ]);
        $contact = UserContact::create([
            'user_id' => $user->id,
            'type' => 'email',
            'value' => 'existing@example.com',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $course = Course::factory()->create(['registration_fee_amount' => 0]);

        $this->post(route('courses.register.start'), [
            'contact_type' => 'email',
            'contact_value' => 'existing@example.com',
            'course_id' => $course->id,
            '_token' => csrf_token(),
        ]);

        $contact2 = UserContact::where('value', 'existing@example.com')->first();
        $this->assertEquals($contact->id, $contact2->id);
        $this->assertEquals(1, User::whereHas('contacts', fn($q) => $q->where('value', 'existing@example.com'))->count());
    }

    public function test_under_18_cannot_self_enroll(): void
    {
        $user = User::factory()->create();
        UserContact::create([
            'user_id' => $user->id,
            'type' => 'email',
            'value' => 'adult@test.com',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $course = Course::factory()->create(['registration_fee_amount' => 0]);

        $response = $this->actingAs($user)->post(route('courses.register.enroll'), [
            'flow' => 'adult',
            'first_name' => 'Child',
            'last_name' => 'Doe',
            'dob' => now()->subYears(10)->format('Y-m-d'),
            'course_ids' => [$course->id],
            '_token' => csrf_token(),
        ]);

        $response->assertSessionHasErrors('dob');
    }

    public function test_duplicate_enrollment_prevented(): void
    {
        $user = User::factory()->create();
        $contact = UserContact::create([
            'user_id' => $user->id,
            'type' => 'email',
            'value' => 'dup@test.com',
            'is_primary' => true,
            'verified_at' => now(),
        ]);

        $student = RegistrationStudent::create([
            'user_id' => $user->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dob' => now()->subYears(25),
        ]);

        $course = Course::factory()->create(['registration_fee_amount' => 0]);

        $enrollmentService = app(\App\Services\Enrollment\EnrollmentService::class);
        $result1 = $enrollmentService->enrollAdultSelf($user, [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dob' => now()->subYears(25)->format('Y-m-d'),
        ], [$course->id], null);

        $result2 = $enrollmentService->enrollAdultSelf($user, [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'dob' => now()->subYears(25)->format('Y-m-d'),
        ], [$course->id], null);

        $count = \App\Models\CourseEnrollment::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->count();
        $this->assertEquals(1, $count);
        $this->assertCount(0, $result2->createdEnrollments);
        $this->assertCount(1, $result2->existingEnrollments);
    }
}
