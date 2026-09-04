<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListDayTimetableForStudentAction;
use App\Domains\Academics\Actions\SaveCalendarDayAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\Academics\Models\SubstitutionAssignment;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The portal home's "tomorrow" strip (E1) and the homework due-date default
 * (E3) both need one day's periods for a student's class. Nothing in Academics
 * read that before — Save, Copy, PreviewConflicts, Backfill and Sync all write
 * or validate.
 *
 * 2026-09-07 is a Monday; the fixtures below are Monday slots.
 */
function seedStudentWithMondayTimetable(): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    $first = makePeriodRow('08:00:00', '08:45:00', 1);
    $second = makePeriodRow('09:00:00', '09:45:00', 2);
    $teacher = makeTeacherRow();
    $subjectOne = makeSubject();
    $subjectTwo = makeSubject();

    // Deliberately saved out of order: the action must sort by period order.
    $entryTwo = app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => $subjectTwo->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => $second->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);
    $entryOne = app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => $subjectOne->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => $first->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    return compact('year', 'class', 'student', 'teacher', 'entryOne', 'entryTwo');
}

it('lists a monday in period order with subject and teacher', function () {
    ['student' => $student] = seedStudentWithMondayTimetable();

    $day = app(ListDayTimetableForStudentAction::class)->execute((int) $student->id, '2026-09-07');

    expect($day['is_school_day'])->toBeTrue()
        ->and($day['class'])->not->toBeNull()
        ->and($day['periods'])->toHaveCount(2)
        ->and($day['periods'][0]['period_name'])->toBe('P1')
        ->and($day['periods'][0]['starts_at'])->toBe('08:00')
        ->and($day['periods'][1]['period_name'])->toBe('P2')
        ->and($day['periods'][1]['starts_at'])->toBe('09:00')
        ->and($day['periods'][0]['subject'])->not->toBeEmpty()
        ->and($day['periods'][0]['teacher'])->not->toBeNull()
        ->and($day['periods'][0]['is_substituted'])->toBeFalse();
});

it('returns no periods for a day the class does not meet', function () {
    ['student' => $student] = seedStudentWithMondayTimetable();

    // 2026-09-08 is a Tuesday; the fixtures are Monday-only.
    $day = app(ListDayTimetableForStudentAction::class)->execute((int) $student->id, '2026-09-08');

    expect($day['periods'])->toBeEmpty()
        ->and($day['is_school_day'])->toBeTrue();
});

it('reports a holiday as not a school day and lists nothing', function () {
    ['student' => $student, 'year' => $year] = seedStudentWithMondayTimetable();

    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $year->id,
        'date' => '2026-09-07',
        'type' => CalendarDayType::Holiday->value,
        'title' => 'National Day',
        'affects_timetable' => true,
    ]);

    $day = app(ListDayTimetableForStudentAction::class)->execute((int) $student->id, '2026-09-07');

    // Must match GenerateExpectedRegistersAction's skip rule, or the strip
    // promises lessons the registers will never create.
    expect($day['is_school_day'])->toBeFalse()
        ->and($day['note'])->toBe('National Day')
        ->and($day['periods'])->toBeEmpty();
});

it('still lists lessons on a calendar day that does not affect the timetable', function () {
    ['student' => $student, 'year' => $year] = seedStudentWithMondayTimetable();

    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $year->id,
        'date' => '2026-09-07',
        'type' => CalendarDayType::ExamDay->value,
        'title' => 'Exam Monday',
        'affects_timetable' => false,
    ]);

    $day = app(ListDayTimetableForStudentAction::class)->execute((int) $student->id, '2026-09-07');

    expect($day['is_school_day'])->toBeTrue()
        ->and($day['periods'])->toHaveCount(2);
});

it('names the substitute once cover is assigned', function () {
    ['student' => $student, 'class' => $class, 'teacher' => $teacher, 'entryOne' => $entryOne] =
        seedStudentWithMondayTimetable();

    $cover = makeTeacherRow();
    $request = SubstitutionRequest::query()->create([
        'timetable_entry_id' => $entryOne->id,
        'date' => '2026-09-07',
        'absent_teacher_id' => $teacher->id,
        'subject_id' => $entryOne->subject_id,
        'classroom_id' => $class->id,
        'period_id' => $entryOne->period_id,
        'status' => 'assigned',
    ]);
    SubstitutionAssignment::query()->create([
        'substitution_request_id' => $request->id,
        'substitute_teacher_id' => $cover->id,
        // assigned_by is a real FK to users; a hardcoded 1 does not exist
        // under RefreshDatabase.
        'assigned_by' => User::factory()->create()->id,
        'assigned_at' => now(),
    ]);

    $day = app(ListDayTimetableForStudentAction::class)->execute((int) $student->id, '2026-09-07');
    $first = collect($day['periods'])->firstWhere('timetable_entry_id', $entryOne->id);

    expect($first['is_substituted'])->toBeTrue()
        ->and($first['substitute_teacher'])->toBe(trim($cover->first_name.' '.$cover->last_name));
});

it('flags an unassigned cover request without naming a substitute', function () {
    ['student' => $student, 'class' => $class, 'teacher' => $teacher, 'entryOne' => $entryOne] =
        seedStudentWithMondayTimetable();

    // Open request, nobody assigned yet: the class is unstaffed. Saying so is
    // more honest than showing the teacher who will not be there.
    SubstitutionRequest::query()->create([
        'timetable_entry_id' => $entryOne->id,
        'date' => '2026-09-07',
        'absent_teacher_id' => $teacher->id,
        'subject_id' => $entryOne->subject_id,
        'classroom_id' => $class->id,
        'period_id' => $entryOne->period_id,
        'status' => 'open',
    ]);

    $day = app(ListDayTimetableForStudentAction::class)->execute((int) $student->id, '2026-09-07');
    $first = collect($day['periods'])->firstWhere('timetable_entry_id', $entryOne->id);

    expect($first['is_substituted'])->toBeTrue()
        ->and($first['substitute_teacher'])->toBeNull()
        ->and($first['cover_status'])->toBe('open');
});

it('returns an empty day for a student on no class roster', function () {
    $student = makeStudent();

    $day = app(ListDayTimetableForStudentAction::class)->execute((int) $student->id, '2026-09-07');

    expect($day['class'])->toBeNull()
        ->and($day['periods'])->toBeEmpty();
});
