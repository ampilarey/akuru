<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListHomeworkForStudentAction;
use App\Domains\Academics\Actions\ResolveNextLessonDateForClassAction;
use App\Domains\Academics\Actions\SaveCalendarDayAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Actions\TickHomeworkAction;
use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\HomeworkTick;
use App\Domains\Academics\Models\LessonLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * E3a — `lesson_logs.homework` has existed since 2025 with nothing reading it.
 * These cover the reader, the due-date default, and the pupil's self-tick.
 *
 * 2026-09-07 and 2026-09-14 are Mondays.
 */
function seedHomeworkClass(): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    $teacher = makeTeacherRow();
    $subject = makeSubject();
    $entry = app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => makePeriodRow()->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    return compact('year', 'class', 'student', 'teacher', 'subject', 'entry');
}

function makeHomeworkLog(array $seed, array $overrides = []): LessonLog
{
    return LessonLog::query()->create(array_merge([
        'teacher_id' => $seed['teacher']->id,
        'subject_id' => $seed['subject']->id,
        'classroom_id' => $seed['class']->id,
        'academic_year_id' => $seed['year']->id,
        'date' => '2026-09-07',
        'taught_summary' => 'Alphabet',
        'homework' => 'Read page 12',
        'homework_due_date' => '2026-09-14',
        'status' => LessonLogStatus::Submitted->value,
        'submitted_at' => now(),
    ], $overrides));
}

it('lists submitted homework for a student on the class', function () {
    $seed = seedHomeworkClass();
    makeHomeworkLog($seed);

    $rows = app(ListHomeworkForStudentAction::class)->execute((int) $seed['student']->id, 3650);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['homework'])->toBe('Read page 12')
        ->and($rows->first()['due_date'])->toBe('2026-09-14')
        ->and($rows->first()['subject'])->toBe($seed['subject']->name)
        ->and($rows->first()['teacher'])->not->toBeNull()
        ->and($rows->first()['is_done'])->toBeFalse();
});

it('hides homework on a register that has not been submitted', function () {
    $seed = seedHomeworkClass();
    // A draft register is the teacher's working copy; showing half-typed
    // homework to a family is worse than showing none.
    makeHomeworkLog($seed, ['status' => LessonLogStatus::Draft->value, 'submitted_at' => null]);

    expect(app(ListHomeworkForStudentAction::class)->execute((int) $seed['student']->id, 3650))->toBeEmpty();
});

it('ignores a lesson with an empty homework box', function () {
    $seed = seedHomeworkClass();
    makeHomeworkLog($seed, ['homework' => null, 'homework_due_date' => null]);

    expect(app(ListHomeworkForStudentAction::class)->execute((int) $seed['student']->id, 3650))->toBeEmpty();
});

it('does not show one class homework to a student on another class', function () {
    $seed = seedHomeworkClass();
    makeHomeworkLog($seed);

    $outsider = makeStudent();

    expect(app(ListHomeworkForStudentAction::class)->execute((int) $outsider->id, 3650))->toBeEmpty();
});

it('records and clears a pupil self-tick', function () {
    $seed = seedHomeworkClass();
    $log = makeHomeworkLog($seed);

    app(TickHomeworkAction::class)->execute((int) $seed['student']->id, (int) $log->id);

    $rows = app(ListHomeworkForStudentAction::class)->execute((int) $seed['student']->id, 3650);
    expect($rows->first()['is_done'])->toBeTrue()
        ->and($rows->first()['done_at'])->not->toBeNull()
        // Rule 10: the tick is time-scoped and carries the lesson's year.
        ->and((int) HomeworkTick::query()->first()->academic_year_id)->toBe((int) $seed['year']->id);

    app(TickHomeworkAction::class)->execute((int) $seed['student']->id, (int) $log->id, false);

    expect(app(ListHomeworkForStudentAction::class)->execute((int) $seed['student']->id, 3650)->first()['is_done'])
        ->toBeFalse()
        ->and(HomeworkTick::query()->count())->toBe(0);
});

it('ticking twice is the same fact, not two rows', function () {
    $seed = seedHomeworkClass();
    $log = makeHomeworkLog($seed);

    app(TickHomeworkAction::class)->execute((int) $seed['student']->id, (int) $log->id);
    app(TickHomeworkAction::class)->execute((int) $seed['student']->id, (int) $log->id);

    expect(HomeworkTick::query()->count())->toBe(1);
});

it('refuses a tick from a student not on the class', function () {
    $seed = seedHomeworkClass();
    $log = makeHomeworkLog($seed);

    app(TickHomeworkAction::class)->execute((int) makeStudent()->id, (int) $log->id);
})->throws(ValidationException::class);

it('counts only outstanding homework for the tile badge', function () {
    $seed = seedHomeworkClass();
    $first = makeHomeworkLog($seed);
    makeHomeworkLog($seed, ['date' => '2026-09-14', 'homework' => 'Read page 20']);

    $reader = app(ListHomeworkForStudentAction::class);
    expect($reader->outstandingCount((int) $seed['student']->id))->toBe(2);

    app(TickHomeworkAction::class)->execute((int) $seed['student']->id, (int) $first->id);

    expect($reader->outstandingCount((int) $seed['student']->id))->toBe(1);
});

it('marks dated unticked homework overdue but never undated homework', function () {
    $seed = seedHomeworkClass();
    $this->travelTo('2026-09-20 09:00');

    $overdue = makeHomeworkLog($seed);
    $undated = makeHomeworkLog($seed, [
        'date' => '2026-09-14',
        'homework' => 'Finish the worksheet',
        'homework_due_date' => null,
    ]);

    $rows = app(ListHomeworkForStudentAction::class)->execute((int) $seed['student']->id, 3650)->keyBy('id');

    expect($rows[$overdue->id]['is_overdue'])->toBeTrue()
        // Homework with no due date is not late, it is undated.
        ->and($rows[$undated->id]['is_overdue'])->toBeFalse();

    app(TickHomeworkAction::class)->execute((int) $seed['student']->id, (int) $overdue->id);

    $after = app(ListHomeworkForStudentAction::class)->execute((int) $seed['student']->id, 3650)->keyBy('id');
    expect($after[$overdue->id]['is_overdue'])->toBeFalse();
});

it('sorts undone work before done work', function () {
    $seed = seedHomeworkClass();
    $early = makeHomeworkLog($seed);
    $later = makeHomeworkLog($seed, [
        'date' => '2026-09-14',
        'homework' => 'Read page 20',
        'homework_due_date' => '2026-09-21',
    ]);

    app(TickHomeworkAction::class)->execute((int) $seed['student']->id, (int) $early->id);

    $rows = app(ListHomeworkForStudentAction::class)->execute((int) $seed['student']->id, 3650);

    expect((int) $rows->first()['id'])->toBe((int) $later->id);
});

it('resolves the next lesson date rather than tomorrow', function () {
    $seed = seedHomeworkClass();

    // 2026-09-07 is a Monday and the class only meets on Mondays; the next
    // lesson is a week later, not the following day.
    $next = app(ResolveNextLessonDateForClassAction::class)
        ->execute((int) $seed['class']->id, (int) $seed['subject']->id, '2026-09-07');

    expect($next)->toBe('2026-09-14');
});

it('skips a holiday when resolving the next lesson date', function () {
    $seed = seedHomeworkClass();

    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $seed['year']->id,
        'date' => '2026-09-14',
        'type' => CalendarDayType::Holiday->value,
        'title' => 'National Day',
        'affects_timetable' => true,
    ]);

    $next = app(ResolveNextLessonDateForClassAction::class)
        ->execute((int) $seed['class']->id, (int) $seed['subject']->id, '2026-09-07');

    expect($next)->toBe('2026-09-21');
});

it('returns no next lesson date for a class with no timetable', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);

    expect(app(ResolveNextLessonDateForClassAction::class)->execute((int) $class->id, null, '2026-09-07'))
        ->toBeNull();
});
