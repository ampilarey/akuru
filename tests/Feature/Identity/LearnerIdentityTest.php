<?php

use App\Domains\Courses\Actions\CheckCertificateEligibilityAction;
use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Actions\IdentityVerificationAction;
use App\Domains\Identity\Models\IdentityVerification;
use App\Domains\Identity\Models\Otp;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * COMMERCE_PARITY_PLAN P3: whoever registers for a course uploads both sides
 * of the learner's ID card — a child's own card for a child (D3). The
 * enrolment goes ahead; the office verifies the card on the enrolment page,
 * and a course certificate waits for that (D2).
 */
beforeEach(function () {
    config(['identity.verification.enforce' => true]);
    Storage::fake('local');
    Storage::fake('public');
    Mail::fake();
});

function learnerCard(): array
{
    return ['id_front' => UploadedFile::fake()->image('front.png', 600, 400), 'id_back' => UploadedFile::fake()->image('back.png', 600, 400)];
}

function learnerWeb()
{
    return test()->withoutLocalizationMiddleware();
}

function learnerFamily(): array
{
    $user = User::factory()->create(['force_password_change' => false]);
    $contact = UserContact::create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '7'.random_int(100000, 999999), 'is_primary' => true, 'verified_at' => now()]);

    return [$user, $contact];
}

function learnerEnrolInput(Course $course): array
{
    return [
        'flow' => 'adult', 'course_ids' => [$course->id], 'first_name' => 'Aishath', 'last_name' => 'Nasir',
        'dob' => now()->subYears(22)->format('Y-m-d'), 'gender' => 'female', 'id_type' => 'national_id', 'national_id' => 'A'.random_int(100000, 999999),
    ];
}

function learnerOffice(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('admin', 'web'));

    return $user;
}

/** Enrol through the real public funnel; returns the enrolment. */
function learnerEnrolled(User $user, UserContact $contact, Course $course): CourseEnrollment
{
    learnerWeb()->actingAs($user)->post(route('courses.register.enroll'), learnerEnrolInput($course) + learnerCard())
        ->assertRedirect(route('courses.register.enroll.otp'));
    Otp::createForContact($contact, 'login', '654321');
    learnerWeb()->actingAs($user)->post(route('courses.register.enroll.confirm'), ['otp_code' => '654321', 'terms_accepted' => '1'])
        ->assertRedirect(route('courses.register.complete'));

    return CourseEnrollment::query()->where('course_id', $course->id)->firstOrFail();
}

it('asks for both sides at registration, and files the card against the learner the enrolment made', function () {
    [$user, $contact] = learnerFamily();
    $course = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);

    learnerWeb()->actingAs($user)->post(route('courses.register.enroll'), learnerEnrolInput($course))
        ->assertSessionHasErrors(['id_front' => __('account.id_learner_needed')]);

    $enrollment = learnerEnrolled($user, $contact, $course);

    $card = IdentityVerification::query()->firstOrFail();
    expect($card->purpose)->toBe('learner')
        ->and($card->status)->toBe('pending')
        ->and((int) $card->student_id)->toBe((int) $enrollment->unified_student_id)
        ->and((int) $card->user_id)->toBe($user->id);
});

it('keeps each learner\'s card to that learner — a parent\'s child has their own', function () {
    $parent = User::factory()->create();
    $child = makeStudent(['user_id' => $parent->id]);
    $sibling = makeStudent(['user_id' => $parent->id]);
    $identity = app(IdentityVerificationAction::class);

    $card = $identity->submit($parent->id, 'learner', learnerCard()['id_front'], learnerCard()['id_back'], $child->id);
    $identity->decide($card->id, learnerOffice()->id, true, null);

    expect($identity->learnerVerified($child->id))->toBeTrue()
        ->and($identity->learnerVerified($sibling->id))->toBeFalse()
        ->and($identity->learnerStatus($sibling->id)['status'])->toBe('none');
});

it('shows the card on the enrolment page, where the office verifies it; the certificate waits until then', function () {
    [$user, $contact] = learnerFamily();
    $course = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);
    $enrollment = learnerEnrolled($user, $contact, $course);
    $office = learnerOffice();

    learnerWeb()->actingAs($office)->get(route('admin.enrollments.show', $enrollment->id))->assertOk()
        ->assertInertia(fn ($page) => $page->where('identity.status', 'pending')->where('identity.student_id', (int) $enrollment->unified_student_id));

    $template = CertificateTemplate::query()->create([
        'name' => 'Given by the teacher', 'kind' => 'manual', 'course_id' => $course->id, 'rules' => [], 'body_html' => '<p>Well done</p>', 'active' => true,
    ]);
    $check = fn () => app(CheckCertificateEligibilityAction::class)->execute($template, (int) $enrollment->unified_student_id, $course->id);
    expect($check())->toBe(['eligible' => false, 'reasons' => [__('account.id_certificate_waits')]]);

    // The family cannot open the images; the office can.
    $card = IdentityVerification::query()->firstOrFail();
    learnerWeb()->actingAs($user)->get(route('identity.document', [$card->id, 'front']))->assertForbidden();
    learnerWeb()->actingAs($office)->get(route('identity.document', [$card->id, 'front']))->assertOk();

    learnerWeb()->actingAs($office)->post(route('identity.decide', $card->id), ['decision' => 'verify'])->assertSessionHasNoErrors();
    expect($check())->toBe(['eligible' => true, 'reasons' => []]);
});

it('lets the family send the card again after a rejection, for their own learner only', function () {
    [$user, $contact] = learnerFamily();
    $course = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);
    $enrollment = learnerEnrolled($user, $contact, $course);
    $studentId = (int) $enrollment->unified_student_id;
    $card = IdentityVerification::query()->firstOrFail();

    learnerWeb()->actingAs(learnerOffice())->post(route('identity.decide', $card->id), ['decision' => 'reject', 'note' => 'The photo is cut off'])
        ->assertSessionHasNoErrors();

    learnerWeb()->actingAs($user)->get(route('my.enrollments'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('enrolments.0.id_card', 'rejected')->where('enrolments.0.id_card_note', 'The photo is cut off'));

    learnerWeb()->actingAs(learnerFamily()[0])->post(route('account.id-card', $studentId), learnerCard())->assertForbidden();
    learnerWeb()->actingAs($user)->post(route('account.id-card', $studentId), learnerCard())->assertSessionHasNoErrors();

    expect(app(IdentityVerificationAction::class)->learnerStatus($studentId)['status'])->toBe('pending')
        ->and(IdentityVerification::query()->count())->toBe(2);
});

it('does not ask again for a card the office already has', function () {
    [$user, $contact] = learnerFamily();
    learnerEnrolled($user, $contact, Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]));
    $second = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);

    // The adult learner is known now, and their card waits with the office.
    learnerWeb()->actingAs($user->fresh())->post(route('courses.register.enroll'), learnerEnrolInput($second))
        ->assertSessionHasNoErrors()->assertRedirect(route('courses.register.enroll.otp'));
});

it('filters the enrolments list by ID card and puts it in the CSV', function () {
    [$user, $contact] = learnerFamily();
    $course = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);
    $enrollment = learnerEnrolled($user, $contact, $course);
    $office = learnerOffice();

    learnerWeb()->actingAs($office)->get(route('admin.enrollments.index', ['id_card' => 'pending']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('enrollments', 1)->where('enrollments.0.id_card', 'pending'));
    learnerWeb()->actingAs($office)->get(route('admin.enrollments.index', ['id_card' => 'verified']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('enrollments', 0));
    learnerWeb()->actingAs($office)->get(route('admin.enrollments.index', ['id_card' => 'none']))->assertOk()
        ->assertInertia(fn ($page) => $page->has('enrollments', 0));

    $csv = learnerWeb()->actingAs($office)->get(route('admin.enrollments.export'))->streamedContent();
    expect($csv)->toContain('ID Card')->toContain('pending');
});

it('speaks Dhivehi and Arabic', function () {
    foreach (['dv', 'ar'] as $locale) {
        foreach (['id_learner_title', 'id_learner_child_title', 'id_learner_needed', 'id_certificate_waits'] as $key) {
            expect(__("account.{$key}", [], $locale))->not->toBe(__("account.{$key}", [], 'en'))->not->toBe("account.{$key}");
        }
    }
});
