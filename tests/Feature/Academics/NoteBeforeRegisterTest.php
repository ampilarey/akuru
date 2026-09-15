<?php

use App\Domains\Academics\Actions\ApproveAbsenceNoteAction;
use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\RecordRegisterAttendanceAction;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\ClassAttendance;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function noteFirstSmsFake(): object
{
    $fake = new class implements SmsSenderInterface
    {
        /** @var list<array{phone: string, message: string}> */
        public array $sent = [];

        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            $this->sent[] = ['phone' => $phoneNumber, 'message' => $message];

            return ['success' => true, 'driver' => 'log'];
        }

        public function sendOtp(string $phoneNumber, string $otp): array
        {
            return $this->sendSms($phoneNumber, "Code: {$otp}", ['type' => 'otp']);
        }
    };

    app()->instance(SmsSenderInterface::class, $fake);

    return $fake;
}

function noteFirstClassAndStudent(): array
{
    $year = makeYear(['name' => '2027-2028', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $teacher = makeTeacherRow();
    $student = makeStudent();
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father', true);
    app(AssignStudentToClassAction::class)->execute($class, $student->id);

    return [$year, $class, $teacher, $student];
}

it('excuses the absence when the note is approved before the register is filled', function () {
    $sms = noteFirstSmsFake();
    [$year, $class, $teacher, $student] = noteFirstClassAndStudent();
    $date = now()->toDateString();

    // Morning: the family says the child is ill, and the office approves it —
    // before anybody has filled a register.
    $note = AbsenceNote::query()->create([
        'student_id' => $student->id,
        'created_by' => (int) $teacher->user_id,
        'date' => $date,
        'reason' => 'Fever, seen by a doctor.',
        'type' => 'illness',
        'status' => 'submitted',
        'affects_attendance' => true,
    ]);

    app(ApproveAbsenceNoteAction::class)->execute($note, (int) $teacher->user_id, 'Doctor\'s note seen');

    // 9am: the teacher fills the register and marks the child absent.
    $log = makeLessonLog(['academic_year_id' => $year->id, 'classroom_id' => $class->id, 'date' => $date]);

    app(RecordRegisterAttendanceAction::class)->execute(
        $log,
        [['student_id' => $student->id, 'status' => 'absent']],
        (int) $teacher->user_id,
    );

    $row = ClassAttendance::query()->where('student_id', $student->id)->firstOrFail();

    expect($row->status)->toBe(AttendanceStatus::Excused)
        ->and((int) $row->absence_note_id)->toBe((int) $note->id)
        // The absence SMS is the visible half of the harm: the family that
        // reported the absence was being texted about it.
        ->and($sms->sent)->toHaveCount(0);
});

/**
 * The boundaries. A rule that excuses more than it should is a worse defect
 * than the one it replaces — an excused row sends no message, tells the family
 * in the portal that none was due, and drops the child out of `unexcused()`,
 * so a child missing for a reason nobody approved would be invisible from
 * three directions. Each of these asserts the SMS still goes out.
 */
it('leaves the absence alone when the note does not excuse it', function () {
    $sms = noteFirstSmsFake();
    [$year, $class, $teacher, $student] = noteFirstClassAndStudent();
    $date = now()->toDateString();

    $note = AbsenceNote::query()->create([
        'student_id' => $student->id,
        'created_by' => (int) $teacher->user_id,
        'date' => $date,
        'reason' => 'Family trip.',
        'type' => 'other',
        'status' => 'submitted',
        'affects_attendance' => false,
    ]);

    app(ApproveAbsenceNoteAction::class)->execute($note, (int) $teacher->user_id);

    $log = makeLessonLog(['academic_year_id' => $year->id, 'classroom_id' => $class->id, 'date' => $date]);
    app(RecordRegisterAttendanceAction::class)->execute(
        $log,
        [['student_id' => $student->id, 'status' => 'absent']],
        (int) $teacher->user_id,
    );

    $row = ClassAttendance::query()->where('student_id', $student->id)->firstOrFail();

    expect($row->status)->toBe(AttendanceStatus::Absent)
        ->and($row->absence_note_id)->toBeNull()
        ->and($sms->sent)->toHaveCount(1);
});

it('leaves the absence alone while the note is still waiting for approval', function () {
    $sms = noteFirstSmsFake();
    [$year, $class, $teacher, $student] = noteFirstClassAndStudent();
    $date = now()->toDateString();

    // Submitted, not approved. The office has not agreed to anything yet, and
    // a note that excused on submission would let a family excuse themselves.
    AbsenceNote::query()->create([
        'student_id' => $student->id,
        'created_by' => (int) $teacher->user_id,
        'date' => $date,
        'reason' => 'Fever.',
        'type' => 'illness',
        'status' => 'submitted',
        'affects_attendance' => true,
    ]);

    $log = makeLessonLog(['academic_year_id' => $year->id, 'classroom_id' => $class->id, 'date' => $date]);
    app(RecordRegisterAttendanceAction::class)->execute(
        $log,
        [['student_id' => $student->id, 'status' => 'absent']],
        (int) $teacher->user_id,
    );

    $row = ClassAttendance::query()->where('student_id', $student->id)->firstOrFail();

    expect($row->status)->toBe(AttendanceStatus::Absent)
        ->and($row->absence_note_id)->toBeNull()
        ->and($sms->sent)->toHaveCount(1);
});

it('does not let yesterday\'s note excuse today', function () {
    $sms = noteFirstSmsFake();
    [$year, $class, $teacher, $student] = noteFirstClassAndStudent();

    $note = AbsenceNote::query()->create([
        'student_id' => $student->id,
        'created_by' => (int) $teacher->user_id,
        'date' => now()->subDay()->toDateString(),
        'reason' => 'Fever.',
        'type' => 'illness',
        'status' => 'submitted',
        'affects_attendance' => true,
    ]);

    app(ApproveAbsenceNoteAction::class)->execute($note, (int) $teacher->user_id);

    $log = makeLessonLog([
        'academic_year_id' => $year->id,
        'classroom_id' => $class->id,
        'date' => now()->toDateString(),
    ]);
    app(RecordRegisterAttendanceAction::class)->execute(
        $log,
        [['student_id' => $student->id, 'status' => 'absent']],
        (int) $teacher->user_id,
    );

    $row = ClassAttendance::query()
        ->where('student_id', $student->id)
        ->whereDate('date', now()->toDateString())
        ->firstOrFail();

    expect($row->status)->toBe(AttendanceStatus::Absent)
        ->and($row->absence_note_id)->toBeNull()
        ->and($sms->sent)->toHaveCount(1);
});

it('does not let one child\'s note excuse another child', function () {
    $sms = noteFirstSmsFake();
    [$year, $class, $teacher, $student] = noteFirstClassAndStudent();
    $date = now()->toDateString();

    $sibling = makeStudent(['first_name' => 'Not', 'last_name' => 'Ill']);
    // With a guardian of their own, so the SMS count below means something.
    // Without one there is nobody to text and the assertion passes on silence.
    app(AttachGuardianAction::class)->execute($sibling, makeGuardian(), 'mother', true);
    app(AssignStudentToClassAction::class)->execute($class, $sibling->id);

    $note = AbsenceNote::query()->create([
        'student_id' => $student->id,
        'created_by' => (int) $teacher->user_id,
        'date' => $date,
        'reason' => 'Fever.',
        'type' => 'illness',
        'status' => 'submitted',
        'affects_attendance' => true,
    ]);

    app(ApproveAbsenceNoteAction::class)->execute($note, (int) $teacher->user_id);

    $log = makeLessonLog(['academic_year_id' => $year->id, 'classroom_id' => $class->id, 'date' => $date]);
    app(RecordRegisterAttendanceAction::class)->execute(
        $log,
        [
            ['student_id' => $student->id, 'status' => 'absent'],
            ['student_id' => $sibling->id, 'status' => 'absent'],
        ],
        (int) $teacher->user_id,
    );

    $mine = ClassAttendance::query()->where('student_id', $student->id)->firstOrFail();
    $theirs = ClassAttendance::query()->where('student_id', $sibling->id)->firstOrFail();

    expect($mine->status)->toBe(AttendanceStatus::Excused)
        ->and($theirs->status)->toBe(AttendanceStatus::Absent)
        ->and($theirs->absence_note_id)->toBeNull()
        // One text, for the child nobody explained.
        ->and($sms->sent)->toHaveCount(1);
});

it('leaves a present mark alone even when a note was approved', function () {
    // The child turned up after all. An approved note must not overwrite what
    // the teacher saw in the room.
    noteFirstSmsFake();
    [$year, $class, $teacher, $student] = noteFirstClassAndStudent();
    $date = now()->toDateString();

    $note = AbsenceNote::query()->create([
        'student_id' => $student->id,
        'created_by' => (int) $teacher->user_id,
        'date' => $date,
        'reason' => 'Fever.',
        'type' => 'illness',
        'status' => 'submitted',
        'affects_attendance' => true,
    ]);

    app(ApproveAbsenceNoteAction::class)->execute($note, (int) $teacher->user_id);

    $log = makeLessonLog(['academic_year_id' => $year->id, 'classroom_id' => $class->id, 'date' => $date]);
    app(RecordRegisterAttendanceAction::class)->execute(
        $log,
        [['student_id' => $student->id, 'status' => 'present']],
        (int) $teacher->user_id,
    );

    $row = ClassAttendance::query()->where('student_id', $student->id)->firstOrFail();

    expect($row->status)->toBe(AttendanceStatus::Present)
        ->and($row->absence_note_id)->toBeNull();
});
