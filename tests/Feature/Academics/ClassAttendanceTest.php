<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\RecordClassAttendanceAction;
use App\Domains\Academics\Actions\RecordDailyAttendanceAction;
use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\ClassAttendance;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function fakeSms(): object
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

it('records per-lesson attendance through the writer and throttles SMS', function () {
    $sms = fakeSms();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $teacher = makeTeacherRow();
    $student = makeStudent();
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father', true);
    app(AssignStudentToClassAction::class)->execute($class, $student->id);

    $log = makeLessonLog([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'classroom_id' => $class->id,
        'date' => now()->toDateString(),
        'period_id' => makePeriodRow()->id,
    ]);

    $writer = app(AttendanceWriterInterface::class);
    expect($writer)->toBeInstanceOf(RecordClassAttendanceAction::class);

    $first = $writer->record(new StudentAttendanceDTO(
        studentId: $student->id,
        classId: $class->id,
        academicYearId: $year->id,
        date: (string) $log->date->toDateString(),
        status: AttendanceStatus::Absent,
        source: AttendanceSource::Register,
        markedBy: (int) $teacher->user_id,
        periodId: $log->period_id,
        lessonLogId: $log->id,
    ));

    expect($first->status)->toBe(AttendanceStatus::Absent)
        ->and(ClassAttendance::query()->count())->toBe(1)
        ->and($sms->sent)->toHaveCount(1)
        ->and($sms->sent[0]['phone'])->toBe('7820288')
        ->and($log->fresh()->absent_count)->toBe(1);

    $writer->record(new StudentAttendanceDTO(
        studentId: $student->id,
        classId: $class->id,
        academicYearId: $year->id,
        date: (string) $log->date->toDateString(),
        status: AttendanceStatus::Absent,
        source: AttendanceSource::Register,
        markedBy: (int) $teacher->user_id,
        periodId: $log->period_id,
        lessonLogId: $log->id,
    ));

    expect(ClassAttendance::query()->count())->toBe(1)
        ->and($sms->sent)->toHaveCount(1);

    $writer->record(new StudentAttendanceDTO(
        studentId: $student->id,
        classId: $class->id,
        academicYearId: $year->id,
        date: (string) $log->date->toDateString(),
        status: AttendanceStatus::Present,
        source: AttendanceSource::Register,
        markedBy: (int) $teacher->user_id,
        periodId: $log->period_id,
        lessonLogId: $log->id,
    ));

    expect(ClassAttendance::query()->sole()->status)->toBe(AttendanceStatus::Present)
        ->and($log->fresh()->present_count)->toBe(1)
        ->and($sms->sent)->toHaveCount(1);
});

it('enforces one daily row per student and rejects daily writes in per-lesson mode', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    $admin = actingPeopleAdmin(['mark_attendance']);
    app(AssignStudentToClassAction::class)->execute($class, $student->id);

    expect(fn () => app(RecordDailyAttendanceAction::class)->execute($class, '2026-08-25', [
        ['student_id' => $student->id, 'status' => 'absent'],
    ], $admin->id))->toThrow(\Illuminate\Validation\ValidationException::class);

    DB::table('settings')->updateOrInsert(
        ['key' => 'attendance_mode'],
        ['value' => 'daily', 'type' => 'string', 'group' => 'academics', 'label' => 'mode'],
    );

    app(RecordDailyAttendanceAction::class)->execute($class, '2026-08-25', [
        ['student_id' => $student->id, 'status' => 'absent'],
    ], $admin->id);
    app(RecordDailyAttendanceAction::class)->execute($class, '2026-08-25', [
        ['student_id' => $student->id, 'status' => 'present'],
    ], $admin->id);

    expect(ClassAttendance::query()->count())->toBe(1)
        ->and(ClassAttendance::query()->sole()->status)->toBe(AttendanceStatus::Present)
        ->and(ClassAttendance::query()->sole()->period_id)->toBeNull()
        ->and(ClassAttendance::query()->sole()->source)->toBe(AttendanceSource::Daily);
});

it('submits the register grid and keeps portal attendance to own children', function () {
    fakeSms();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $teacher = makeTeacherRow();
    $mine = makeStudent(['first_name' => 'Mine']);
    $other = makeStudent(['first_name' => 'Other']);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($mine, $guardian, 'father', true);
    app(AssignStudentToClassAction::class)->execute($class, $mine->id);
    app(AssignStudentToClassAction::class)->execute($class, $other->id);

    $log = makeLessonLog([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'classroom_id' => $class->id,
        'date' => now()->toDateString(),
        'period_id' => makePeriodRow()->id,
    ]);

    $teacherUser = User::query()->findOrFail($teacher->user_id);
    foreach (['registers.fill', 'mark_attendance'] as $permission) {
        Permission::findOrCreate($permission, 'web');
        $teacherUser->givePermissionTo($permission);
    }

    $this->withoutLocalizationMiddleware()
        ->actingAs($teacherUser)
        ->put(route('academics.registers.update', $log), [
            'taught_summary' => 'Surah review',
            'attendance' => [
                ['student_id' => $mine->id, 'status' => 'absent'],
                ['student_id' => $other->id, 'status' => 'present'],
            ],
        ])
        ->assertRedirect();

    expect($log->fresh()->status->value)->toBe('submitted')
        ->and(ClassAttendance::query()->count())->toBe(2);

    $admin = actingPeopleAdmin(['view_attendance', 'manage_attendance']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.attendance.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Attendance/Index')
            ->has('rows', 2)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.attendance.export', ['academic_year_id' => $year->id, 'kind' => 'unexcused']))
        ->assertOk()
        ->streamedContent();
    expect($csv)->toContain('Mine');

    $parent = User::query()->findOrFail($guardian->user_id);
    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->get(route('portal.attendance'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Attendance')
            ->has('rows', 1)
            ->where('rows.0.student_id', $mine->id)
            ->where('rows.0.guardian_notified', true)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->get(route('portal.attendance', ['student_id' => $other->id]))
        ->assertForbidden();
});

it('lists chronic absences at the threshold', function () {
    fakeSms();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    $teacher = makeTeacherRow();
    app(AssignStudentToClassAction::class)->execute($class, $student->id);

    $writer = app(AttendanceWriterInterface::class);
    $period = makePeriodRow();
    foreach (['2026-08-10', '2026-08-11', '2026-08-12', '2026-08-13', '2026-08-14'] as $date) {
        $writer->record(new StudentAttendanceDTO(
            studentId: $student->id,
            classId: $class->id,
            academicYearId: $year->id,
            date: $date,
            status: AttendanceStatus::Absent,
            source: AttendanceSource::Register,
            markedBy: (int) $teacher->user_id,
            periodId: $period->id,
        ));
    }

    $chronic = app(\App\Domains\Academics\Actions\ListClassAttendanceAction::class)->chronic($year->id, 5);
    expect($chronic)->toHaveCount(1)
        ->and($chronic->first()['absent_days'])->toBe(5);
});
