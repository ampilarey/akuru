<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Models\SmsReceipt;
use App\Domains\Notifications\Providers\NotificationsServiceProvider;
use App\Domains\Notifications\Services\LogSmsSender;
use App\Domains\Notifications\Services\SmsGatewayService;
use App\Domains\Notifications\Support\LiveSms;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function rebindSmsSender(): SmsSenderInterface
{
    app()->forgetInstance(SmsSenderInterface::class);
    (new NotificationsServiceProvider(app()))->register();

    return app(SmsSenderInterface::class);
}

it('binds a log SMS sender outside production even if SMS_LIVE is on', function (string $env) {
    Http::fake();
    config(['services.sms.live' => true]);
    app()->instance('env', $env);

    $sender = rebindSmsSender();
    expect($sender)->toBeInstanceOf(LogSmsSender::class)
        ->and(LiveSms::allowed())->toBeFalse();

    $sender->sendSms('7820288', 'Akuru Institute: test');
    Http::assertNothingSent();
    expect(app(SmsGatewayService::class)->sendSms('7820288', 'leak'))
        ->toMatchArray(['error_code' => 'SMS_LIVE_DISABLED']);
    Http::assertNothingSent();
})->with(['local', 'staging', 'testing']);

it('fails closed in production unless SMS_LIVE is an explicit true', function (mixed $live) {
    Http::fake();
    config(['services.sms.live' => $live]);
    app()->instance('env', 'production');

    expect(LiveSms::allowed())->toBeFalse()
        ->and(rebindSmsSender())->toBeInstanceOf(LogSmsSender::class);

    rebindSmsSender()->sendSms('7972434', 'should stay fake');
    Http::assertNothingSent();
})->with([false, null, 'false', '0', '', 'yes', 'on', 0]);

it('resolves the live gateway only in production with SMS_LIVE', function (mixed $live) {
    config(['services.sms.live' => $live]);
    app()->instance('env', 'production');

    expect(LiveSms::allowed())->toBeTrue()
        ->and(rebindSmsSender())->toBeInstanceOf(SmsGatewayService::class);
})->with([true, 'true', 'TRUE', 1, '1']);

it('records a fake absence send with channel, number, body, and timestamp and makes no HTTP', function () {
    Http::fake();
    expect(app(SmsSenderInterface::class))->toBeInstanceOf(LogSmsSender::class);

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

    $receipt = SmsReceipt::query()->sole();
    expect($receipt->channel)->toBe('sms')
        ->and($receipt->phone)->toBe($guardian->phone)
        ->and($receipt->body)->toContain('Fatima')
        ->and($receipt->body)->toContain('absent')
        ->and($receipt->driver)->toBe('log')
        ->and($receipt->success)->toBeTrue()
        ->and($receipt->sent_at)->not->toBeNull();

    $sender = app(SmsSenderInterface::class);
    expect($sender)->toBeInstanceOf(LogSmsSender::class)
        ->and($sender->sent)->toHaveCount(1)
        ->and($sender->sent[0]['channel'])->toBe('sms')
        ->and($sender->sent[0]['phone'])->toBe($guardian->phone)
        ->and($sender->sent[0]['body'])->toContain('Fatima')
        ->and($sender->sent[0]['timestamp'])->not->toBeEmpty();

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
