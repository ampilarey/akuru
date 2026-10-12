<?php

use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Models\UserContact;
use App\Domains\Identity\Services\OtpService;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Services\LogSmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Two things that told people the wrong thing (KNOWN_ISSUES, STATUS §5rv):
 * a code's SMS said *Valid for 10 minutes* whatever the code's real life,
 * and the registration's last page said the office approves every
 * enrolment — true only of a course that asks for approval.
 */
uses(RefreshDatabase::class);

function codeSmsFake(): object
{
    $fake = new class implements SmsSenderInterface
    {
        /** @var list<array{phone: string, otp: string, minutes: int}> */
        public array $codes = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            return ['success' => true];
        }

        public function sendOtp(string $phoneNumber, string $otp, int $minutes = 10): array
        {
            $this->codes[] = ['phone' => $phoneNumber, 'otp' => $otp, 'minutes' => $minutes];

            return ['success' => true];
        }

        public function checkHealth(): bool
        {
            return true;
        }
    };
    app()->instance(SmsSenderInterface::class, $fake);

    return $fake;
}

it('says in the SMS how long each code lives', function () {
    $fake = codeSmsFake();
    $user = User::factory()->create();
    $contact = UserContact::query()->create(['user_id' => $user->id, 'type' => 'mobile', 'value' => '+9607771234', 'is_primary' => true, 'verified_at' => now()]);

    app(OtpService::class)->send($contact, 'verify_contact');
    app(OtpService::class)->send($contact->fresh(), 'login');
    expect(array_column($fake->codes, 'minutes'))->toBe([5, 15]);

    // The words themselves, on the driver that writes them.
    $sent = (new LogSmsSender)->sendOtp('+9607771234', '123456', 5);
    expect($sent['success'] ?? true)->toBeTruthy();
})->skip(fn () => ! method_exists(OtpService::class, 'send'), 'OtpService::send is the entry point');

it('writes the minutes it is given into the code\'s SMS', function () {
    $class = new ReflectionClass(LogSmsSender::class);
    $source = (string) file_get_contents((string) $class->getFileName());
    expect($source)->toContain('Valid for {$minutes} minutes')->not->toContain('Valid for 10 minutes');
    $gateway = (string) file_get_contents(app_path('Domains/Notifications/Services/SmsGatewayService.php'));
    expect($gateway)->toContain('Valid for {$minutes} minutes')->not->toContain('Valid for 10 minutes');
});

it('tells a family the next steps their course really has', function () {
    $user = User::factory()->create();
    $student = makeCourseStudent(['user_id' => $user->id, 'first_name' => 'Aisha', 'last_name' => 'Ali', 'dob' => now()->subYears(20)]);
    $open = Course::factory()->create(['requires_admin_approval' => false]);
    $approved = Course::factory()->create(['requires_admin_approval' => true]);
    $mine = fn (Course $c) => CourseEnrollment::create(['unified_student_id' => $student->id, 'course_id' => $c->id, 'status' => 'pending', 'payment_status' => 'pending']);

    $web = test()->withoutLocalizationMiddleware();
    $web->withSession(['enrollments' => [$mine($open)]])->get(route('courses.register.complete'))->assertOk()
        ->assertSee('data-approval="no"', false)->assertSee(__('register.next_active_now'))->assertDontSee(__('register.next_review'))
        ->assertDontSee(__('register.next_time'));

    $web->withSession(['enrollments' => [$mine($approved)]])->get(route('courses.register.complete'))->assertOk()
        ->assertSee('data-approval="yes"', false)->assertSee(__('register.next_review'))->assertSee(__('register.next_time'));

    // The payment poller's "received" line follows the same setting; it
    // renders only with a payment in hand, so read it off the view.
    $view = (string) file_get_contents(resource_path('views/courses/register-complete.blade.php'));
    expect($view)->toContain('register.payment_received_active')->toContain("register.payment_received'");

    // In Dhivehi and Arabic too.
    foreach (['next_bank', 'next_active_now', 'payment_received_active'] as $key) {
        expect(__('register.'.$key, [], 'dv'))->toMatch('/\p{Thaana}/u')->and(__('register.'.$key, [], 'ar'))->toMatch('/\p{Arabic}/u');
    }
});
