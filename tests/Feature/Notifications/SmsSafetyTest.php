<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Providers\NotificationsServiceProvider;
use App\Domains\Notifications\Services\LogSmsSender;
use App\Domains\Notifications\Services\SmsGatewayService;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('binds a log SMS sender outside production even if SMS_LIVE is on', function (string $env) {
    Http::fake();
    config(['services.sms.live' => true]);
    app()->instance('env', $env);
    app()->forgetInstance(SmsSenderInterface::class);
    (new NotificationsServiceProvider(app()))->register();

    $sender = app(SmsSenderInterface::class);
    expect($sender)->toBeInstanceOf(LogSmsSender::class);

    $sender->sendSms('7820288', 'Akuru Institute: test');
    Http::assertNothingSent();
    expect(app(SmsGatewayService::class)->sendSms('7820288', 'leak'))
        ->toMatchArray(['error_code' => 'SMS_LIVE_DISABLED']);
    Http::assertNothingSent();
})->with(['local', 'staging', 'testing']);

it('resolves the live gateway only in production with SMS_LIVE', function () {
    config(['services.sms.live' => true]);
    app()->instance('env', 'production');
    app()->forgetInstance(SmsSenderInterface::class);
    (new NotificationsServiceProvider(app()))->register();

    expect(app(SmsSenderInterface::class))->toBeInstanceOf(SmsGatewayService::class);
});

it('marks the portal attendance row as parent notified without HTTP', function () {
    Http::fake();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $teacher = makeTeacherRow();
    $student = makeStudent(['first_name' => 'Fatima']);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father', true);
    app(AssignStudentToClassAction::class)->execute($class, $student->id);

    $date = now()->toDateString();
    app(AttendanceWriterInterface::class)->record(new StudentAttendanceDTO(
        studentId: $student->id,
        classId: $class->id,
        academicYearId: $year->id,
        date: $date,
        status: AttendanceStatus::Absent,
        source: AttendanceSource::Register,
        markedBy: (int) $teacher->user_id,
        periodId: makePeriodRow()->id,
    ));

    Http::assertNothingSent();

    $parent = User::query()->findOrFail($guardian->user_id);
    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->get(route('portal.attendance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Attendance')
            ->where('rows.0.guardian_notified', true)
            ->where('rows.0.status', 'absent')
        );
});
