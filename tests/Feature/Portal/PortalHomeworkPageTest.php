<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\HomeworkTick;
use App\Domains\Academics\Models\LessonLog;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * E3a walked over HTTP: the pupil sees the homework their teacher wrote in the
 * register, ticks it, and the parent sees the same list read-only.
 */
function seedPortalHomework(): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    $teacher = makeTeacherRow();
    $subject = makeSubject();
    app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => makePeriodRow()->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    $log = LessonLog::query()->create([
        'teacher_id' => $teacher->id,
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
        'academic_year_id' => $year->id,
        'date' => now()->toDateString(),
        'taught_summary' => 'Alphabet',
        'homework' => 'Read page 12',
        'homework_due_date' => now()->addDays(7)->toDateString(),
        'status' => LessonLogStatus::Submitted->value,
        'submitted_at' => now(),
    ]);

    return [
        'student' => $student,
        'studentUser' => User::query()->find($student->user_id),
        'log' => $log,
    ];
}

it('shows a pupil their homework and lets them tick it', function () {
    ['studentUser' => $studentUser, 'student' => $student, 'log' => $log] = seedPortalHomework();

    $this->withoutLocalizationMiddleware()
        ->actingAs($studentUser)
        ->get('/portal/homework')
        ->assertOk()
        ->assertSee('Read page 12');

    $this->withoutLocalizationMiddleware()
        ->actingAs($studentUser)
        ->post('/portal/homework/'.$log->id.'/tick', ['student_id' => $student->id, 'done' => true])
        ->assertRedirect('/portal/homework');

    expect(HomeworkTick::query()->where('student_id', $student->id)->count())->toBe(1);
});

it('shows a parent the same list but refuses a tick on the child behalf', function () {
    ['student' => $student, 'log' => $log] = seedPortalHomework();
    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, 'father');
    $guardianUser = User::query()->find($guardian->user_id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get('/portal/homework')
        ->assertOk()
        ->assertSee('Read page 12');

    // The tick is the pupil's own statement about their own work.
    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->post('/portal/homework/'.$log->id.'/tick', ['student_id' => $student->id, 'done' => true])
        ->assertForbidden();

    expect(HomeworkTick::query()->count())->toBe(0);
});

it('refuses a tick for a student the viewer is not', function () {
    ['studentUser' => $studentUser, 'log' => $log] = seedPortalHomework();
    $other = makeStudent();

    $this->withoutLocalizationMiddleware()
        ->actingAs($studentUser)
        ->post('/portal/homework/'.$log->id.'/tick', ['student_id' => $other->id, 'done' => true])
        ->assertForbidden();
});
