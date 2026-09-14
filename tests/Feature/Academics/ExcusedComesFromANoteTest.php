<?php

use App\Domains\Academics\Actions\ApproveAbsenceNoteAction;
use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\ClassAttendance;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * KNOWN_ISSUES #15 — "Teacher grid offers `excused` / `left_early`".
 *
 * S2_SPEC §S2.4 says where an excusal comes from: *"approving an
 * `absence_note` (existing flow) with `affects_attendance=true` flips matching
 * absent rows → excused and links `absence_note_id`."* A guardian explains the
 * absence, a teacher approves the explanation, and the row becomes excused
 * **with the note attached**.
 *
 * The register grid offered `excused` as a fourth button, and the write path
 * took it — `absence_note_id` null, no note, no approval. That is not only a
 * confusing button, which is how the defect was filed. It is silent:
 *
 *  - `RecordClassAttendanceAction::maybeNotify()` notifies on absent and (by
 *    setting) late, and stays **quiet on excused**. So marking a child excused
 *    rather than absent means the family is never told the child is missing.
 *  - Since the #17 fix, the portal's own column reads the same rule and shows
 *    such a row as **"Not applicable"** — the product actively telling the
 *    parent no message was due.
 *  - `ListClassAttendanceAction::unexcused()` excludes excused rows, so the
 *    child also drops out of the chronic-absence list.
 *
 * One mis-click, and a missing child is invisible from three directions.
 *
 * The rule therefore lives in the **writer**, not the grid: `Excused` requires
 * an `absenceNoteId`. Every route into `class_attendance` goes through
 * `AttendanceWriterInterface` — `AttendanceWriterTest` is the architecture test
 * that pins that — so the register grid, the daily grid, a CSV import and a
 * future biometric device are all covered by one check. Hiding the button is
 * the cosmetic half; this repo has already learned once (SPEC §44/§45) that a
 * route relying on a hidden button is not guarded.
 */
function excusedTestSmsFake(): object
{
    $fake = new class implements App\Domains\Notifications\Contracts\SmsSenderInterface
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

    app()->instance(App\Domains\Notifications\Contracts\SmsSenderInterface::class, $fake);

    return $fake;
}

function excusedTestClassAndStudent(): array
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

it('refuses an excused mark that no approved note stands behind', function () {
    [$year, $class, $teacher, $student] = excusedTestClassAndStudent();

    $record = fn () => app(AttendanceWriterInterface::class)->record(new StudentAttendanceDTO(
        studentId: $student->id,
        classId: $class->id,
        academicYearId: $year->id,
        date: now()->toDateString(),
        status: AttendanceStatus::Excused,
        source: AttendanceSource::Register,
        markedBy: (int) $teacher->user_id,
    ));

    expect($record)->toThrow(ValidationException::class);

    // And nothing was written — a rejected mark must not leave a row behind
    // that later reads as an excusal.
    expect(ClassAttendance::query()->count())->toBe(0);
});

it('still lets an approved note excuse the absence it explains', function () {
    $sms = excusedTestSmsFake();
    [$year, $class, $teacher, $student] = excusedTestClassAndStudent();
    $date = now()->toDateString();

    app(AttendanceWriterInterface::class)->record(new StudentAttendanceDTO(
        studentId: $student->id,
        classId: $class->id,
        academicYearId: $year->id,
        date: $date,
        status: AttendanceStatus::Absent,
        source: AttendanceSource::Register,
        markedBy: (int) $teacher->user_id,
    ));

    // The absent mark is the one that tells the family. This is the behaviour
    // the excused shortcut was skipping.
    expect($sms->sent)->toHaveCount(1);

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

    $row = ClassAttendance::query()->where('student_id', $student->id)->firstOrFail();

    expect($row->status)->toBe(AttendanceStatus::Excused)
        ->and((int) $row->absence_note_id)->toBe((int) $note->id);
});

it('offers the teacher only the statuses a teacher may set', function () {
    // The grid and the rule must agree, or the grid shows a button that 422s.
    $settable = array_map(fn (AttendanceStatus $s) => $s->value, AttendanceStatus::teacherSettable());

    expect($settable)->not->toContain(AttendanceStatus::Excused->value)
        ->and($settable)->toContain(AttendanceStatus::Absent->value)
        ->and($settable)->toContain(AttendanceStatus::Present->value)
        ->and($settable)->toContain(AttendanceStatus::Late->value);
});
