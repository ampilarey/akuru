<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListClassAttendanceAction;
use App\Domains\Academics\Actions\ListTardySummaryAction;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\ClassAttendance;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * E10 — lateness, aggregated.
 *
 * `class_attendance.minutes_late` has been written since 2026-08 and never
 * summed: a pupil ten minutes late every day shows up nowhere, because
 * chronic() counts only full absences.
 */
function seedAttendanceClass(): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    return compact('year', 'class', 'student');
}

function markAttendance(array $seed, string $date, AttendanceStatus $status, ?int $minutesLate = null, int $periodKey = 0): ClassAttendance
{
    return ClassAttendance::query()->create([
        'student_id' => $seed['student']->id,
        'class_id' => $seed['class']->id,
        'academic_year_id' => $seed['year']->id,
        'date' => $date,
        'period_key' => $periodKey,
        'status' => $status->value,
        'minutes_late' => $minutesLate,
        'marked_by' => User::factory()->create()->id,
    ]);
}

function setTardyRule(int $perAbsence): void
{
    DB::table('settings')->updateOrInsert(
        ['key' => 'attendance_tardies_per_absence'],
        ['value' => (string) $perAbsence]
    );
}

it('sums late marks and minutes per student', function () {
    $seed = seedAttendanceClass();
    markAttendance($seed, '2026-09-01', AttendanceStatus::Late, 10);
    markAttendance($seed, '2026-09-02', AttendanceStatus::Late, 5);
    markAttendance($seed, '2026-09-03', AttendanceStatus::Present);

    $row = app(ListTardySummaryAction::class)->execute()->first();

    expect($row['tardies'])->toBe(2)
        ->and($row['minutes_late'])->toBe(15)
        ->and($row['early_departures'])->toBe(0);
});

it('counts early departures separately from lateness', function () {
    $seed = seedAttendanceClass();
    markAttendance($seed, '2026-09-01', AttendanceStatus::Late, 10);
    markAttendance($seed, '2026-09-02', AttendanceStatus::LeftEarly);

    $row = app(ListTardySummaryAction::class)->execute()->first();

    expect($row['tardies'])->toBe(1)
        ->and($row['early_departures'])->toBe(1);
});

it('returns nothing for a student who is never late', function () {
    $seed = seedAttendanceClass();
    markAttendance($seed, '2026-09-01', AttendanceStatus::Present);
    markAttendance($seed, '2026-09-02', AttendanceStatus::Absent);

    expect(app(ListTardySummaryAction::class)->execute())->toBeEmpty();
});

it('applies no tardy-to-absence conversion when no rule is set', function () {
    $seed = seedAttendanceClass();
    foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
        markAttendance($seed, $date, AttendanceStatus::Late, 5);
    }

    // A school that has not chosen a number must not have one applied behind
    // its back — the default is off, not three.
    $row = app(ListTardySummaryAction::class)->execute()->first();

    expect($row['tardies_per_absence'])->toBe(0)
        ->and($row['absences_from_tardies'])->toBe(0)
        ->and($row['effective_absences'])->toBe(0);
});

it('converts late marks to absences once a rule is set', function () {
    $seed = seedAttendanceClass();
    setTardyRule(3);
    foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04'] as $date) {
        markAttendance($seed, $date, AttendanceStatus::Late, 5);
    }
    markAttendance($seed, '2026-09-07', AttendanceStatus::Absent);

    $row = app(ListTardySummaryAction::class)->execute()->first();

    // Four lates under a three-per-rule is one absence, not one and a third.
    expect($row['tardies'])->toBe(4)
        ->and($row['absences_from_tardies'])->toBe(1)
        ->and($row['absent_days'])->toBe(1)
        ->and($row['effective_absences'])->toBe(2);
});

it('does not round a partial rule up', function () {
    $seed = seedAttendanceClass();
    setTardyRule(3);
    markAttendance($seed, '2026-09-01', AttendanceStatus::Late, 5);
    markAttendance($seed, '2026-09-02', AttendanceStatus::Late, 5);

    // Two lates are not yet an absence.
    expect(app(ListTardySummaryAction::class)->execute()->first()['absences_from_tardies'])->toBe(0);
});

it('leaves the chronic absence figure untouched', function () {
    $seed = seedAttendanceClass();
    setTardyRule(1); // the most aggressive rule possible
    foreach (['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04', '2026-09-08'] as $date) {
        markAttendance($seed, $date, AttendanceStatus::Late, 5);
    }

    // The whole design decision: lateness is reported beside the absence
    // numbers, never folded into them. Changing what chronic() returns would
    // move a figure the school already reads, with nothing to show why.
    expect(app(ListClassAttendanceAction::class)->chronic(null, 5))->toBeEmpty()
        ->and(app(ListTardySummaryAction::class)->execute()->first()['effective_absences'])->toBe(5);
});

it('honours the date window on both halves of a row', function () {
    $seed = seedAttendanceClass();
    setTardyRule(2);
    markAttendance($seed, '2026-08-01', AttendanceStatus::Late, 5);
    markAttendance($seed, '2026-08-02', AttendanceStatus::Absent);
    markAttendance($seed, '2026-09-01', AttendanceStatus::Late, 5);
    markAttendance($seed, '2026-09-02', AttendanceStatus::Late, 5);
    markAttendance($seed, '2026-09-03', AttendanceStatus::Absent);

    $row = app(ListTardySummaryAction::class)
        ->execute(['from' => '2026-09-01', 'to' => '2026-09-30'])
        ->first();

    // Lateness and absences must cover the same window, or the two numbers on
    // one row describe different periods.
    expect($row['tardies'])->toBe(2)
        ->and($row['absent_days'])->toBe(1)
        ->and($row['effective_absences'])->toBe(2);
});

it('scopes to a class when asked', function () {
    $seed = seedAttendanceClass();
    markAttendance($seed, '2026-09-01', AttendanceStatus::Late, 5);

    $other = seedAttendanceClass();
    markAttendance($other, '2026-09-01', AttendanceStatus::Late, 5);

    expect(app(ListTardySummaryAction::class)->execute(['class_id' => $seed['class']->id]))
        ->toHaveCount(1);
});

it('sorts the latest pupils first', function () {
    $a = seedAttendanceClass();
    markAttendance($a, '2026-09-01', AttendanceStatus::Late, 5);

    $b = ['year' => $a['year'], 'class' => $a['class'], 'student' => makeStudent()];
    app(AssignStudentToClassAction::class)->execute($b['class'], (int) $b['student']->id);
    foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $date) {
        markAttendance($b, $date, AttendanceStatus::Late, 5);
    }

    expect(app(ListTardySummaryAction::class)->execute()->first()['student_id'])
        ->toBe((int) $b['student']->id);
});

it('treats a missing minutes value as zero rather than failing', function () {
    $seed = seedAttendanceClass();
    markAttendance($seed, '2026-09-01', AttendanceStatus::Late, null);

    expect(app(ListTardySummaryAction::class)->execute()->first()['minutes_late'])->toBe(0);
});
