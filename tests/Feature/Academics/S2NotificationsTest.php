<?php

use App\Domains\Academics\Actions\NotifyAdminDailyDigestAction;
use App\Domains\Academics\Actions\NotifyUnfilledRegistersAction;
use App\Domains\Academics\Actions\ReviewSchoolRequestAction;
use App\Domains\Academics\Actions\SaveBehaviorRecordAction;
use App\Domains\Academics\Enums\BehaviorType;
use App\Domains\Academics\Enums\SchoolRequestStatus;
use App\Domains\Academics\Enums\SchoolRequestType;
use App\Domains\Academics\Models\SchoolRequest;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Models\UserNotification;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function fakeS2Sms(): object
{
    $fake = new class implements SmsSenderInterface
    {
        /** @var list<array{phone: string, message: string}> */
        public array $sent = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            $this->sent[] = ['phone' => $phoneNumber, 'message' => $message];

            return ['success' => true];
        }
    };

    app()->instance(SmsSenderInterface::class, $fake);

    return $fake;
}

function setSetting(string $key, string $value): void
{
    DB::table('settings')->updateOrInsert(['key' => $key], ['key' => $key, 'value' => $value]);
}

it('reminds a teacher once per day about unfilled registers', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $teacher = makeTeacherRow();

    makeLessonLog([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'classroom_id' => $class->id,
        'date' => now()->subDay()->toDateString(),
        'period_id' => makePeriodRow()->id,
    ]);

    expect(app(NotifyUnfilledRegistersAction::class)->execute())->toBe(1);

    $notice = UserNotification::query()->where('user_id', $teacher->user_id)->sole();
    expect($notice->category)->toBe('academics')
        ->and($notice->data['unfilled_count'])->toBe(1);

    // Second run the same day is a no-op.
    expect(app(NotifyUnfilledRegistersAction::class)->execute())->toBe(0)
        ->and(UserNotification::query()->where('user_id', $teacher->user_id)->count())->toBe(1);
});

it('notifies the requester when a request is approved or rejected', function () {
    $requester = actingPeopleAdmin(['registers.manage']);

    $request = SchoolRequest::query()->create([
        'type' => SchoolRequestType::Other->value,
        'requester_id' => $requester->id,
        'payload' => [],
        'reason' => 'Personal',
        'status' => SchoolRequestStatus::Pending->value,
    ]);

    app(ReviewSchoolRequestAction::class)->execute(
        $request,
        SchoolRequestStatus::Rejected,
        $requester->id,
        'Not this week',
    );

    $notice = UserNotification::query()->where('user_id', $requester->id)->sole();
    expect($notice->data['status'])->toBe('rejected')
        ->and($notice->message)->toContain('Not this week');
});

it('keeps the admin digest silent unless the setting is on', function () {
    $admin = actingPeopleAdmin(['registers.manage']);

    expect(app(NotifyAdminDailyDigestAction::class)->execute())->toBe(0)
        ->and(UserNotification::query()->where('user_id', $admin->id)->count())->toBe(0);

    setSetting('admin_daily_digest', '1');

    expect(app(NotifyAdminDailyDigestAction::class)->execute())->toBe(1);
    // And only once per admin per day.
    expect(app(NotifyAdminDailyDigestAction::class)->execute())->toBe(0);
});

it('only SMSes parents about behaviour when opted in, and never for compliments', function () {
    $sms = fakeS2Sms();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $student = makeStudent(['first_name' => 'Mine']);
    app(AttachGuardianAction::class)->execute($student, makeGuardian(), 'father', true);
    $teacher = actingPeopleAdmin(['behavior.record']);

    $log = fn (string $type) => app(SaveBehaviorRecordAction::class)->execute([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'type' => $type,
        'category' => 'conduct',
        'description' => 'Test entry',
        'date' => '2026-08-25',
        'recorded_by' => $teacher->id,
        'parent_visible' => true,
    ], null, $teacher->id);

    // Setting off by default.
    $log(BehaviorType::Incident->value);
    expect($sms->sent)->toHaveCount(0);

    setSetting('behavior_notify_parents', '1');

    // Compliments stay quiet even when opted in.
    $log(BehaviorType::Compliment->value);
    expect($sms->sent)->toHaveCount(0);

    $log(BehaviorType::Incident->value);
    expect($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0]['phone'])->toBe('7820288');
});

it('does not notify parents about a record they cannot see', function () {
    $sms = fakeS2Sms();
    setSetting('behavior_notify_parents', '1');
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $student = makeStudent(['first_name' => 'Hidden']);
    app(AttachGuardianAction::class)->execute($student, makeGuardian(), 'father', true);
    $teacher = actingPeopleAdmin(['behavior.record']);

    app(SaveBehaviorRecordAction::class)->execute([
        'student_id' => $student->id,
        'academic_year_id' => $year->id,
        'type' => BehaviorType::Incident->value,
        'category' => 'safety',
        'description' => 'Staff only note',
        'date' => '2026-08-25',
        'recorded_by' => $teacher->id,
        'parent_visible' => false,
    ], null, $teacher->id);

    expect($sms->sent)->toHaveCount(0);
});
