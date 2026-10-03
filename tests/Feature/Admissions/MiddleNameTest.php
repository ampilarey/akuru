<?php

use App\Domains\Courses\Actions\IssueCertificateAction;
use App\Domains\Courses\Actions\SaveCertificateTemplateAction;
use App\Domains\Courses\Enums\CertificateKind;
use App\Domains\Courses\Models\Course;
use App\Domains\Identity\Models\Otp;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\People\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * C17 slice R4 (STATUS §5og). The owner, 2026-10-03: "Now name has only 2
 * columns ... we use in Maldives 1st name, 2 names and last name". Every
 * registration form asks first, middle (optional) and last; the student keeps
 * all three, and the account's name reads them in order.
 */
beforeEach(function () {
    config(['identity.verification.enforce' => false]);
    $this->withoutLocalizationMiddleware();
});

it('keeps a middle name from the details form on the student and in the account name', function () {
    $user = User::factory()->create(['name' => 'Asif Moosa Ibrahim', 'force_password_change' => false]);
    $contact = UserContact::create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '7'.random_int(100000, 999999), 'is_primary' => true, 'verified_at' => now()]);
    $course = Course::factory()->create(['registration_fee_amount' => 0, 'requires_admin_approval' => false]);

    // A name with no profile yet is split first / middle / last on the form.
    $this->actingAs($user)->withSession(['pending_selected_course_ids' => [$course->id]])
        ->get(route('courses.register.continue'))->assertOk()
        ->assertSee('name="middle_name"', false)
        ->assertSee('value="Asif"', false)
        ->assertSee('value="Moosa"', false)
        ->assertSee('value="Ibrahim"', false)
        ->assertSee('Middle name(s) (optional)');

    $this->actingAs($user)->post(route('courses.register.enroll'), [
        'flow' => 'adult', 'course_ids' => [$course->id], 'first_name' => 'Aishath', 'middle_name' => 'Fathimath  Shazna', 'last_name' => 'Ali',
        'dob' => now()->subYears(24)->format('Y-m-d'), 'gender' => 'female', 'id_type' => 'national_id', 'national_id' => 'A'.random_int(100000, 999999),
    ])->assertSessionHasNoErrors()->assertRedirect(route('courses.register.enroll.otp'));
    Otp::createForContact($contact, 'login', '654321');
    $this->actingAs($user)->post(route('courses.register.enroll.confirm'), ['otp_code' => '654321', 'terms_accepted' => '1'])
        ->assertRedirect(route('courses.register.complete'));

    $student = Student::query()->where('user_id', $user->id)->sole();
    expect($student->first_name)->toBe('Aishath')
        ->and($student->middle_name)->toBe('Fathimath  Shazna')
        ->and($student->last_name)->toBe('Ali')
        ->and($student->full_name)->toBe('Aishath Fathimath  Shazna Ali');
});

it('leaves the middle name empty when there is none, and the full name has no gap', function () {
    $student = new Student(['first_name' => 'Ali', 'middle_name' => null, 'last_name' => 'Hassan']);
    expect($student->full_name)->toBe('Ali Hassan');
    $student->middle_name = 'Moosa';
    expect($student->full_name)->toBe('Ali Moosa Hassan');
});

it('asks for the middle name on the checkout form, and says it in three languages', function () {
    $course = Course::factory()->create(['registration_fee_amount' => 0, 'status' => 'open', 'workflow_status' => 'published']);
    $this->get(route('courses.checkout.show', $course))->assertOk()->assertSee('name="middle_name"', false);

    expect(trans('account.name_middle', [], 'en'))->toBe('Middle name(s) (optional)')
        ->and(trans('account.name_middle', [], 'dv'))->not->toBe('account.name_middle')
        ->and(trans('account.name_middle', [], 'ar'))->not->toBe('account.name_middle');
});

it('prints the middle name on a certificate and on its public verify page', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $year = makeYear(['is_current' => true, 'status' => 'active']);
    $student = makeStudent(['first_name' => 'Aishath', 'middle_name' => 'Shazna', 'last_name' => 'Ali']);
    $template = app(SaveCertificateTemplateAction::class)->execute(['name' => 'Completion', 'kind' => CertificateKind::Manual->value, 'active' => true, 'created_by' => $admin->id]);
    $issued = app(IssueCertificateAction::class)->execute(['certificate_template_id' => $template->id, 'student_id' => $student->id, 'academic_year_id' => $year->id], $admin->id);

    $this->get(route('public.certificates.verify', $issued->public_id))->assertOk()->assertSee('Aishath Shazna Ali');
});
