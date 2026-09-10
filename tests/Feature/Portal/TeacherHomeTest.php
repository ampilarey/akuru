<?php

use App\Domains\Academics\Actions\ListDayTimetableForTeacherAction;
use App\Domains\Academics\Actions\SaveCalendarDayAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\Academics\Enums\LessonLogStatus;
use App\Domains\Academics\Models\SubstitutionAssignment;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Identity\Models\User;
use App\Domains\Portal\Actions\ComposeTeacherHomeAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * E1b — a teacher's own home.
 *
 * E1 shipped the family home and left teachers redirected straight into the
 * register list, which is a task queue rather than a home. These cover the new
 * teacher-scoped day read (including cover in both directions), the composed
 * payload, and the landing change that makes any of it reachable.
 *
 * 2026-09-07 is a Monday; the fixtures below are Monday slots.
 */
function seedTeacherMonday(): array
{
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $teacher = makeTeacherRow();
    $second = makePeriodRow('09:00:00', '09:45:00', 2);
    $first = makePeriodRow('08:00:00', '08:45:00', 1);

    // Saved out of order on purpose: the action must sort by period order.
    $entryTwo = app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => $second->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);
    $entryOne = app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => $teacher->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => $first->id,
        'room_id' => makeRoomRow()->id,
        'is_active' => true,
    ]);

    return compact('year', 'class', 'teacher', 'entryOne', 'entryTwo');
}

it('lists a teachers monday in period order with class and room', function () {
    ['teacher' => $teacher] = seedTeacherMonday();

    $day = app(ListDayTimetableForTeacherAction::class)->execute((int) $teacher->id, '2026-09-07');

    expect($day['is_school_day'])->toBeTrue()
        ->and($day['periods'])->toHaveCount(2)
        ->and($day['periods'][0]['period_name'])->toBe('P1')
        ->and($day['periods'][0]['starts_at'])->toBe('08:00')
        ->and($day['periods'][1]['period_name'])->toBe('P2')
        ->and($day['periods'][0]['class'])->not->toBeEmpty()
        ->and($day['periods'][0]['is_mine'])->toBeTrue()
        ->and($day['periods'][0]['is_covering_for'])->toBeNull();
});

it('returns nothing for a day the teacher does not teach', function () {
    ['teacher' => $teacher] = seedTeacherMonday();

    // 2026-09-08 is a Tuesday.
    expect(app(ListDayTimetableForTeacherAction::class)->execute((int) $teacher->id, '2026-09-08')['periods'])
        ->toBe([]);
});

it('says a holiday plainly rather than showing an empty day', function () {
    ['teacher' => $teacher, 'year' => $year] = seedTeacherMonday();
    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $year->id,
        'date' => '2026-09-07',
        'title' => 'Founders Day',
        'type' => CalendarDayType::Holiday->value,
        'affects_timetable' => true,
    ]);

    $day = app(ListDayTimetableForTeacherAction::class)->execute((int) $teacher->id, '2026-09-07');

    // The same rule the register generator uses, so the strip cannot promise
    // periods the registers will never create.
    expect($day['is_school_day'])->toBeFalse()
        ->and($day['note'])->toBe('Founders Day')
        ->and($day['periods'])->toBe([]);
});

it('still shows a period of mine that somebody else is covering', function () {
    ['teacher' => $teacher, 'class' => $class, 'entryOne' => $entryOne] = seedTeacherMonday();
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
        'assigned_by' => User::factory()->create()->id,
        'assigned_at' => now(),
    ]);

    $day = app(ListDayTimetableForTeacherAction::class)->execute((int) $teacher->id, '2026-09-07');
    $mine = collect($day['periods'])->firstWhere('timetable_entry_id', $entryOne->id);

    // Hiding it would be worse than showing it: they need to know it is handled.
    expect($day['periods'])->toHaveCount(2)
        ->and($mine['is_substituted'])->toBeTrue()
        ->and($mine['substitute_teacher'])->toBe(trim($cover->first_name.' '.$cover->last_name));
});

it('puts a period i am covering onto my own day', function () {
    ['teacher' => $absent, 'class' => $class, 'entryOne' => $entryOne] = seedTeacherMonday();
    $cover = makeTeacherRow();
    $request = SubstitutionRequest::query()->create([
        'timetable_entry_id' => $entryOne->id,
        'date' => '2026-09-07',
        'absent_teacher_id' => $absent->id,
        'subject_id' => $entryOne->subject_id,
        'classroom_id' => $class->id,
        'period_id' => $entryOne->period_id,
        'status' => 'assigned',
    ]);
    SubstitutionAssignment::query()->create([
        'substitution_request_id' => $request->id,
        'substitute_teacher_id' => $cover->id,
        'assigned_by' => User::factory()->create()->id,
        'assigned_at' => now(),
    ]);

    $day = app(ListDayTimetableForTeacherAction::class)->execute((int) $cover->id, '2026-09-07');

    // A cover that does not appear on the substitute's own timetable is how a
    // class sits unattended.
    expect($day['periods'])->toHaveCount(1)
        ->and($day['periods'][0]['is_mine'])->toBeFalse()
        ->and($day['periods'][0]['is_covering_for'])->toBe(trim($absent->first_name.' '.$absent->last_name));
});

it('does not claim a period whose cover nobody has accepted', function () {
    ['teacher' => $absent, 'class' => $class, 'entryOne' => $entryOne] = seedTeacherMonday();
    $other = makeTeacherRow();
    SubstitutionRequest::query()->create([
        'timetable_entry_id' => $entryOne->id,
        'date' => '2026-09-07',
        'absent_teacher_id' => $absent->id,
        'subject_id' => $entryOne->subject_id,
        'classroom_id' => $class->id,
        'period_id' => $entryOne->period_id,
        'status' => 'open',
    ]);

    // An open request means nobody has agreed. Putting it on someone's day
    // would tell them they are teaching a class they were never given.
    expect(app(ListDayTimetableForTeacherAction::class)->execute((int) $other->id, '2026-09-07')['periods'])
        ->toBe([]);
});

it('composes a home with tiles counting only what the teacher owes', function () {
    ['teacher' => $teacher, 'class' => $class, 'year' => $year] = seedTeacherMonday();
    $mine = makeLessonLog([
        'year' => $year,
        'teacher_id' => $teacher->id,
        'classroom_id' => $class->id,
        'date' => now()->subDay()->toDateString(),
        'status' => LessonLogStatus::Expected->value,
    ]);
    // Somebody else's unfilled register must not land in my badge.
    makeLessonLog([
        'year' => $year,
        'classroom_id' => $class->id,
        'date' => now()->subDay()->toDateString(),
        'status' => LessonLogStatus::Expected->value,
    ]);

    $home = app(ComposeTeacherHomeAction::class)->execute((int) $teacher->user_id);
    $registers = collect($home['tiles'])->firstWhere('key', 'registers');

    expect((int) $home['teacherId'])->toBe((int) $teacher->id)
        ->and($home['unfilled'])->toHaveCount(1)
        ->and((int) $home['unfilled'][0]['id'])->toBe((int) $mine->id)
        ->and($registers['badge'])->toBe(1)
        ->and($registers['status'])->toBe('1 to fill');
});

it('finds the next teaching day rather than only looking at tomorrow', function () {
    ['teacher' => $teacher] = seedTeacherMonday();
    // A Wednesday: the next Monday is five days out, well past "tomorrow".
    $this->travelTo(\Carbon\Carbon::parse('2026-09-02 07:00:00', config('app.timezone')));

    $home = app(ComposeTeacherHomeAction::class)->execute((int) $teacher->user_id);

    expect($home['next'])->not->toBeNull()
        ->and($home['next']['date'])->toBe('2026-09-07')
        ->and($home['next']['day_name'])->toBe('Monday')
        ->and($home['next']['periods'])->toHaveCount(2);
});

it('gives a staff account with no teacher record a partial home rather than an error', function () {
    $home = app(ComposeTeacherHomeAction::class)->execute((int) User::factory()->create()->id);

    // An empty page would be worse than a partial one: messages and notices are
    // true for anybody.
    expect($home['teacherId'])->toBeNull()
        ->and($home['today']['periods'])->toBe([])
        ->and(collect($home['tiles'])->pluck('key')->all())->toContain('messages', 'announcements');
});

it('renders the home for register staff and refuses everyone else', function () {
    ['teacher' => $teacher] = seedTeacherMonday();
    $staff = User::query()->findOrFail($teacher->user_id);
    \Spatie\Permission\Models\Permission::findOrCreate('registers.fill', 'web');
    $staff->givePermissionTo('registers.fill');

    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->get(route('portal.teacher'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/TeacherHome')
            ->where('teacherId', (int) $teacher->id)
            ->has('tiles')
            ->has('today.periods')
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get(route('portal.teacher'))
        ->assertForbidden();
});

it('lands a teacher on their home instead of the register list', function () {
    ['teacher' => $teacher] = seedTeacherMonday();
    $staff = User::query()->findOrFail($teacher->user_id);
    \Spatie\Permission\Models\Permission::findOrCreate('registers.fill', 'web');
    $staff->givePermissionTo('registers.fill');
    $role = \Spatie\Permission\Models\Role::findOrCreate('teacher', 'web');
    $staff->assignRole($role);

    // Without this the whole slice is unreachable — E7's precedence is
    // unchanged, only where the `registers` landing points.
    $this->withoutLocalizationMiddleware()
        ->actingAs($staff)
        ->get('/dashboard')
        ->assertRedirect(route('portal.teacher'));
});
