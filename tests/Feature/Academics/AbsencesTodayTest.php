<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Academics\Actions\ListAbsencesForDayAction;
use App\Domains\Academics\Actions\SubmitAbsenceNoteAction;
use App\Domains\Academics\Enums\AbsenceNoteStatus;
use App\Domains\Academics\Enums\AttendanceSource;
use App\Domains\Academics\Enums\AttendanceStatus;
use App\Domains\Academics\Models\AbsenceNote;
use App\Domains\Academics\Models\ClassAttendance;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * E10b — who is not in today, and whether anybody knows why.
 *
 * Attendance is per lesson and absence notes sit in a separate review queue, so
 * "which children are missing, and which of those are unexplained" meant
 * reading two screens and doing the join in your head. The join is the feature:
 * an absence with a note is administration, an absence with none is a
 * telephone call.
 */
function absenceSeed(): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    return compact('year', 'class', 'student');
}

function markAbsent(array $seed, $student, string $date, ?int $periodId = null, string $status = 'absent'): ClassAttendance
{
    return ClassAttendance::query()->create([
        'academic_year_id' => $seed['year']->id,
        'class_id' => $seed['class']->id,
        'student_id' => $student->id,
        'date' => $date,
        'period_id' => $resolved = ($periodId ?? makePeriodRow('08:00:00', '08:45:00', 1)->id),
        // The unique index is on (student, date, period_key), not period_id —
        // RecordClassAttendanceAction sets it and a raw fixture must too, or
        // four periods collide at the default 0.
        'period_key' => $resolved,
        'status' => $status,
        'source' => AttendanceSource::Daily->value,
        'marked_by' => User::factory()->create()->id,
    ]);
}

it('counts a child absent for four periods as one absent child', function () {
    $seed = absenceSeed();
    foreach ([1, 2, 3, 4] as $order) {
        markAbsent($seed, $seed['student'], '2026-09-10', makePeriodRow('0'.(7 + $order).':00:00', '0'.(7 + $order).':45:00', $order)->id);
    }

    $payload = app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10']);

    // Collapsing that is the difference between a list you act on and a list
    // you scroll.
    expect($payload['students'])->toHaveCount(1)
        ->and($payload['counts']['total'])->toBe(1)
        ->and($payload['students'][0]['periods_missed'])->toBe(4)
        ->and($payload['students'][0]['periods'])->toHaveCount(4);
});

it('marks an absence with no note as the one to call about', function () {
    $seed = absenceSeed();
    markAbsent($seed, $seed['student'], '2026-09-10');

    $payload = app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10']);

    expect($payload['students'][0]['is_unexplained'])->toBeTrue()
        ->and($payload['students'][0]['note_status'])->toBeNull()
        ->and($payload['counts']['unexplained'])->toBe(1);
});

it('treats a note still awaiting review as an explanation', function () {
    $seed = absenceSeed();
    markAbsent($seed, $seed['student'], '2026-09-10');
    app(SubmitAbsenceNoteAction::class)->execute([
        'student_id' => $seed['student']->id,
        'created_by' => User::factory()->create()->id,
        'date' => '2026-09-10',
        'reason' => 'Fever',
    ]);

    $payload = app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10']);

    // Somebody told the school. The office should not be telephoning them while
    // it waits for a review.
    expect($payload['students'][0]['is_unexplained'])->toBeFalse()
        ->and($payload['students'][0]['note_status'])->toBe(AbsenceNoteStatus::Submitted->value)
        ->and($payload['students'][0]['note_reason'])->toBe('Fever')
        ->and($payload['counts']['unexplained'])->toBe(0);
});

it('does not let a rejected note explain an absence', function () {
    $seed = absenceSeed();
    markAbsent($seed, $seed['student'], '2026-09-10');
    $note = app(SubmitAbsenceNoteAction::class)->execute([
        'student_id' => $seed['student']->id,
        'created_by' => User::factory()->create()->id,
        'date' => '2026-09-10',
        'reason' => 'Not convincing',
    ]);
    $note->update(['status' => AbsenceNoteStatus::Rejected->value]);

    // That is what rejecting it meant.
    expect(app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10'])['students'][0]['is_unexplained'])
        ->toBeTrue();
});

it('prefers an approved note over a pending one', function () {
    $seed = absenceSeed();
    markAbsent($seed, $seed['student'], '2026-09-10');
    $submit = app(SubmitAbsenceNoteAction::class);
    $submit->execute([
        'student_id' => $seed['student']->id,
        'created_by' => User::factory()->create()->id,
        'date' => '2026-09-10',
        'reason' => 'Pending one',
    ]);
    $approved = $submit->execute([
        'student_id' => $seed['student']->id,
        'created_by' => User::factory()->create()->id,
        'date' => '2026-09-10',
        'reason' => 'Approved one',
    ]);
    $approved->update(['status' => AbsenceNoteStatus::Approved->value]);

    $row = app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10'])['students'][0];

    expect($row['note_status'])->toBe(AbsenceNoteStatus::Approved->value)
        ->and($row['note_reason'])->toBe('Approved one');
});

it('does not count a note written for a different day', function () {
    $seed = absenceSeed();
    markAbsent($seed, $seed['student'], '2026-09-10');
    app(SubmitAbsenceNoteAction::class)->execute([
        'student_id' => $seed['student']->id,
        'created_by' => User::factory()->create()->id,
        'date' => '2026-09-09',
        'reason' => 'Yesterday',
    ]);

    expect(app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10'])['students'][0]['is_unexplained'])
        ->toBeTrue();
});

it('ignores present and late children', function () {
    $seed = absenceSeed();
    $alsoIn = makeStudent();
    app(AssignStudentToClassAction::class)->execute($seed['class'], (int) $alsoIn->id);
    $period = makePeriodRow('08:00:00', '08:45:00', 1)->id;
    markAbsent($seed, $seed['student'], '2026-09-10', $period, AttendanceStatus::Present->value);
    markAbsent($seed, $alsoIn, '2026-09-10', $period, AttendanceStatus::Late->value);

    // Late is a different problem, and E10a already reports it.
    expect(app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10'])['students'])->toBe([]);
});

it('includes an excused absence, which is still a child who is not there', function () {
    $seed = absenceSeed();
    markAbsent($seed, $seed['student'], '2026-09-10', null, AttendanceStatus::Excused->value);

    expect(app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10'])['students'])->toHaveCount(1);
});

it('sorts unexplained absences to the top', function () {
    $seed = absenceSeed();
    $explained = makeStudent();
    app(AssignStudentToClassAction::class)->execute($seed['class'], (int) $explained->id);
    $period = makePeriodRow('08:00:00', '08:45:00', 1)->id;
    markAbsent($seed, $explained, '2026-09-10', $period);
    markAbsent($seed, $seed['student'], '2026-09-10', $period);
    app(SubmitAbsenceNoteAction::class)->execute([
        'student_id' => $explained->id,
        'created_by' => User::factory()->create()->id,
        'date' => '2026-09-10',
        'reason' => 'Dentist',
    ]);

    // The list is a call sheet, not a report.
    $rows = app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10'])['students'];

    expect($rows[0]['is_unexplained'])->toBeTrue()
        ->and($rows[1]['is_unexplained'])->toBeFalse();
});

it('filters to unexplained only, and by class', function () {
    $seed = absenceSeed();
    $other = makeClass($seed['year'], 'Grade 2', 'B');
    $elsewhere = makeStudent();
    app(AssignStudentToClassAction::class)->execute($other, (int) $elsewhere->id);
    $period = makePeriodRow('08:00:00', '08:45:00', 1)->id;
    markAbsent($seed, $seed['student'], '2026-09-10', $period);
    ClassAttendance::query()->create([
        'academic_year_id' => $seed['year']->id,
        'class_id' => $other->id,
        'student_id' => $elsewhere->id,
        'date' => '2026-09-10',
        'period_id' => $period,
        'period_key' => $period,
        'status' => AttendanceStatus::Absent->value,
        'source' => AttendanceSource::Daily->value,
        'marked_by' => User::factory()->create()->id,
    ]);
    app(SubmitAbsenceNoteAction::class)->execute([
        'student_id' => $seed['student']->id,
        'created_by' => User::factory()->create()->id,
        'date' => '2026-09-10',
        'reason' => 'Fever',
    ]);

    $action = app(ListAbsencesForDayAction::class);

    expect($action->execute(['date' => '2026-09-10'])['students'])->toHaveCount(2)
        ->and($action->execute(['date' => '2026-09-10', 'only_unexplained' => true])['students'])->toHaveCount(1)
        ->and($action->execute(['date' => '2026-09-10', 'class_id' => (int) $seed['class']->id])['students'])->toHaveCount(1);
});

it('renders for attendance staff, refuses everyone else, and exports', function () {
    $seed = absenceSeed();
    markAbsent($seed, $seed['student'], '2026-09-10');
    $staff = actingPeopleAdmin(['manage_attendance']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->get(route('academics.attendance.absences', ['date' => '2026-09-10']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Attendance/AbsencesToday')
            ->has('students', 1)
            ->where('counts.unexplained', 1)
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->get(route('academics.attendance.absences.export', ['date' => '2026-09-10']));
    $csv->assertOk();
    expect($csv->streamedContent())->toContain('unexplained');
});

it('keeps the absence list away from staff without attendance permission', function () {
    // Its own test: actingAs persists for the rest of a test, so a refusal
    // tacked after a signed-in assertion would not be testing a refusal.
    $this->withoutLocalizationMiddleware()
        ->actingAs(actingPeopleAdmin([]))
        ->get(route('academics.attendance.absences'))
        ->assertForbidden();
});

it('defaults to today when no date is given', function () {
    $seed = absenceSeed();
    markAbsent($seed, $seed['student'], now()->toDateString());

    $payload = app(ListAbsencesForDayAction::class)->execute();

    expect($payload['date'])->toBe(now()->timezone(config('app.timezone'))->toDateString())
        ->and($payload['students'])->toHaveCount(1);
});

it('returns an empty day rather than failing when nobody is absent', function () {
    absenceSeed();

    $payload = app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10']);

    expect($payload['students'])->toBe([])
        ->and($payload['counts'])->toBe(['total' => 0, 'unexplained' => 0]);
});

it('does not leak a note belonging to another child', function () {
    $seed = absenceSeed();
    $other = makeStudent();
    markAbsent($seed, $seed['student'], '2026-09-10');
    app(SubmitAbsenceNoteAction::class)->execute([
        'student_id' => $other->id,
        'created_by' => User::factory()->create()->id,
        'date' => '2026-09-10',
        'reason' => 'Somebody elses reason',
    ]);

    expect(AbsenceNote::query()->count())->toBe(1)
        ->and(app(ListAbsencesForDayAction::class)->execute(['date' => '2026-09-10'])['students'][0])
        ->toMatchArray(['is_unexplained' => true, 'note_reason' => null]);
});
