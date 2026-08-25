<?php

use App\Domains\Academics\Actions\ApproveAbsenceNoteAction;
use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Contracts\AttendanceWriterInterface;
use App\Domains\Academics\DTOs\StudentAttendanceDTO;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\ClassAttendance;
use App\Domains\Identity\Models\User;
use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('approving an absence note excuses matching absent rows via the writer', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    $teacher = makeTeacherRow();
    $period = makePeriodRow();
    app(AssignStudentToClassAction::class)->execute($class, $student->id);

    app()->instance(SmsSenderInterface::class, new class implements SmsSenderInterface
    {
        public function sendSms(string $phoneNumber, string $message, array $options = []): array
        {
            return ['success' => true];
        }
    });

    app(AttendanceWriterInterface::class)->record(new StudentAttendanceDTO(
        studentId: $student->id,
        classId: $class->id,
        academicYearId: $year->id,
        date: '2026-08-25',
        status: AttendanceStatus::Absent,
        source: AttendanceSource::Register,
        markedBy: (int) $teacher->user_id,
        periodId: $period->id,
    ));

    $note = AbsenceNote::query()->create([
        'student_id' => $student->id,
        'created_by' => User::factory()->create()->id,
        'date' => '2026-08-25',
        'period_id' => $period->id,
        'reason' => 'Clinic',
        'type' => 'illness',
        'status' => 'submitted',
        'affects_attendance' => true,
    ]);

    $admin = actingPeopleAdmin(['manage_attendance']);
    app(ApproveAbsenceNoteAction::class)->execute($note, $admin->id, 'Medical note seen');

    $row = ClassAttendance::query()->sole();
    expect($note->fresh()->status)->toBe('approved')
        ->and($row->status)->toBe(AttendanceStatus::Excused)
        ->and($row->absence_note_id)->toBe($note->id);
});

it('lets a parent submit a note for their child and forbids another child', function () {
    $mine = makeStudent(['first_name' => 'Mine']);
    $other = makeStudent(['first_name' => 'Other']);
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($mine, $guardian, 'father', true);
    $parent = User::query()->findOrFail($guardian->user_id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->post(route('portal.absence-notes.store'), [
            'student_id' => $mine->id,
            'date' => '2026-08-25',
            'reason' => 'Fever',
            'type' => 'illness',
        ])
        ->assertRedirect();

    expect(AbsenceNote::query()->where('student_id', $mine->id)->count())->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->post(route('portal.absence-notes.store'), [
            'student_id' => $other->id,
            'date' => '2026-08-26',
            'reason' => 'Nope',
            'type' => 'other',
        ])
        ->assertForbidden();

    $admin = actingPeopleAdmin(['manage_attendance']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.absence-notes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/AbsenceNotes/Index')
            ->has('notes', 1)
        );
});
