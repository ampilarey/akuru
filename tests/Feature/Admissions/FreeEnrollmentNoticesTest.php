<?php

use App\Domains\Admissions\Actions\AnnounceFreeEnrollmentsAction;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\Otp;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Mail\AdminFreeEnrollmentMail;
use App\Mail\FreeEnrollmentConfirmedMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

/**
 * KNOWN_ISSUES #24, the last piece: the free-enrollment half.
 *
 * SPEC §41 ends on "Enrollment should dispatch an enrollment-created event.
 * Notifications should listen to that event. **Enrollment code must not
 * directly call notification implementation classes.**" The paid half moved in
 * two earlier slices. This one stayed in `CourseRegistrationController`, which
 * held two `protected` methods that queued two Mailables and sent an SMS — mail
 * composed inside a controller, which is rule 5 as well as §41.
 *
 * What is pinned here is the behaviour, not the arrangement: a free enrollment
 * tells the family and the office, a paid one stays silent until its money is
 * confirmed (rule 12 — never announce before confirmation), and the decision
 * between them is made somewhere a test can reach without driving the whole
 * public registration flow.
 */
function freeEnrollmentFixture(string $paymentStatus = 'not_required'): array
{
    $payer = User::factory()->create(['email' => 'family'.uniqid().'@example.test']);
    $student = makeRegistrationStudent(['user_id' => $payer->id]);

    $course = Course::factory()->create([
        'workflow_status' => 'published',
        'status' => 'open',
    ]);

    $enrollment = CourseEnrollment::query()->create([
        'course_id' => $course->id,
        'student_id' => $student->id,
        'status' => 'pending',
        'payment_status' => $paymentStatus,
        'enrollment_type' => $paymentStatus === 'not_required' ? 'free' : 'paid',
    ]);

    return compact('payer', 'student', 'course', 'enrollment');
}

it('tells the family and the office when an enrollment needs no payment', function () {
    Mail::fake();
    config(['mail.admin_notification_address' => 'office@example.test']);

    ['payer' => $payer, 'course' => $course, 'enrollment' => $enrollment] = freeEnrollmentFixture();

    app(AnnounceFreeEnrollmentsAction::class)->execute($payer->id, [$enrollment]);

    Mail::assertQueued(FreeEnrollmentConfirmedMail::class, fn ($mail) => $mail->hasTo($payer->email));
    Mail::assertQueued(AdminFreeEnrollmentMail::class, fn ($mail) => $mail->hasTo('office@example.test'));

    // The course reaches the subject line rather than "Unknown course".
    Mail::assertQueued(
        AdminFreeEnrollmentMail::class,
        fn ($mail) => str_contains($mail->envelope()->subject, $course->title)
    );
});

it('stays silent for a paid enrollment, because money is not confirmed yet', function () {
    Mail::fake();

    // Rule 12: access and announcements follow confirmed money, never the
    // creation of a row or a redirect back from the gateway. A paid enrollment
    // is announced by the payment webhook, through a different event.
    ['payer' => $payer, 'enrollment' => $enrollment] = freeEnrollmentFixture('pending');

    app(AnnounceFreeEnrollmentsAction::class)->execute($payer->id, [$enrollment]);

    Mail::assertNothingQueued();
});

it('sends the family SMS through the sender contract', function () {
    Mail::fake();

    ['payer' => $payer, 'course' => $course, 'enrollment' => $enrollment] = freeEnrollmentFixture();
    $payer->contacts()->create(['type' => 'mobile', 'value' => '9607'.random_int(100000, 999999)]);

    $sent = [];
    app()->instance(SmsSenderInterface::class, new class($sent) implements SmsSenderInterface
    {
        public function __construct(public array &$sent) {}

        public function sendSms(string $to, string $message, array $options = []): array
        {
            $this->sent[] = [$to, $message];

            return ['success' => true, 'message_id' => 'spy', 'status' => 'sent'];
        }

        public function sendOtp(string $phoneNumber, string $otp): array
        {
            return $this->sendSms($phoneNumber, "Code: {$otp}", ['type' => 'otp']);
        }
    });

    app(AnnounceFreeEnrollmentsAction::class)->execute($payer->id, [$enrollment]);

    expect($sent)->toHaveCount(1)
        ->and($sent[0][1])->toContain($course->title);
});

it('leaves no Mailable named anywhere in the registration controller', function () {
    // §41's actual rule, and the one a later slice is most likely to undo by
    // adding "just one more" notification where the enrollment is created.
    $source = (string) file_get_contents(
        base_path('app/Domains/Admissions/Http/Controllers/CourseRegistrationController.php')
    );

    expect(stripPhpComments($source))
        ->not->toContain('App\\Mail\\')
        ->not->toContain('Mail::to');
});

it('reaches the family through the real public registration flow', function () {
    // The Action tests above pin the rule; this one pins the wiring, because
    // the wiring is what a controller refactor breaks. It drives the actual
    // routes a family uses — enroll, then OTP confirm — and asserts the
    // confirmation was queued at the end of it.
    Mail::fake();
    config(['mail.admin_notification_address' => 'office@example.test']);

    $user = User::factory()->create(['force_password_change' => false, 'email' => 'realflow@example.test']);
    $contact = UserContact::create([
        'user_id' => $user->id,
        'type' => 'mobile',
        'value' => '7820288',
        'is_primary' => true,
        'verified_at' => now(),
    ]);

    $course = Course::factory()->create([
        'registration_fee_amount' => 0,
        'requires_admin_approval' => false,
    ]);

    $this->actingAs($user)->post(route('courses.register.enroll'), [
        'flow' => 'adult',
        'course_ids' => [$course->id],
        'first_name' => 'Ali',
        'last_name' => 'Mohamed',
        'dob' => now()->subYears(20)->format('Y-m-d'),
        'gender' => 'male',
        'id_type' => 'national_id',
        'national_id' => 'A'.random_int(100000, 999999),
    ])->assertRedirect(route('courses.register.enroll.otp'));

    Otp::createForContact($contact, 'login', '654321');

    $this->actingAs($user)->post(route('courses.register.enroll.confirm'), [
        'otp_code' => '654321',
        'terms_accepted' => '1',
    ])->assertRedirect(route('courses.register.complete'));

    $this->assertDatabaseHas('course_enrollments', ['course_id' => $course->id]);

    Mail::assertQueued(FreeEnrollmentConfirmedMail::class, fn ($mail) => $mail->hasTo('realflow@example.test'));
    Mail::assertQueued(AdminFreeEnrollmentMail::class, fn ($mail) => $mail->hasTo('office@example.test'));
});
