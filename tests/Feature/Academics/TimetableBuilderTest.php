<?php

use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Models\SubstitutionAssignment;
use App\Domains\Academics\Models\SubstitutionRequest;
use App\Domains\Academics\Models\TeacherAbsence;
use App\Domains\Academics\Models\Timetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function builderAdmin(): \App\Domains\Identity\Models\User
{
    return actingPeopleAdmin(['manage_timetables', 'timetables.allow_conflict']);
}

function periodSlot(array $overrides = []): array
{
    $year = $overrides['year'] ?? makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    unset($overrides['year']);

    $class = $overrides['class'] ?? makeClass($year);
    unset($overrides['class']);

    $period = $overrides['period'] ?? makePeriodRow();
    unset($overrides['period']);

    return array_merge([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => makeTeacherRow()->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => $period->id,
        'room_id' => makeRoomRow()->id,
    ], $overrides);
}

it('loads the timetable builder for a permitted admin', function () {
    $admin = builderAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $period = makePeriodRow();
    $subject = makeSubject();
    $teacher = makeTeacherRow();
    $room = makeRoomRow('Studio');

    app(SaveTimetableEntryAction::class)->execute(periodSlot([
        'year' => $year,
        'class' => $class,
        'period' => $period,
        'subject_id' => $subject->id,
        'teacher_id' => $teacher->id,
        'room_id' => $room->id,
    ]));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.timetable.index', [
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'view' => 'class',
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Timetable/Builder')
            ->where('yearId', $year->id)
            ->where('classId', $class->id)
            ->where('view', 'class')
            ->has('entries', 1)
            ->has('periods', 1)
            ->has('subjects')
            ->has('teachers')
            ->has('rooms')
        );
});

it('stores a period slot from the builder', function () {
    $admin = builderAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $period = makePeriodRow();
    $subject = makeSubject();
    $teacher = makeTeacherRow();
    $room = makeRoomRow('Hall B');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.timetable.store'), [
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'period_id' => $period->id,
            'day_of_week' => 'tuesday',
            'room_id' => $room->id,
        ])
        ->assertRedirect(route('academics.timetable.index', [
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'view' => 'class',
        ]));

    $entry = Timetable::query()->sole();
    expect($entry->day_of_week)->toBe('tuesday')
        ->and($entry->period_id)->toBe($period->id)
        ->and($entry->teacher_id)->toBe($teacher->id)
        ->and($entry->room_id)->toBe($room->id)
        ->and($entry->room)->toBe('Hall B');
});

it('returns preview conflicts as json', function () {
    $admin = builderAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $period = makePeriodRow();
    $teacher = makeTeacherRow();
    $classA = makeClass($year, 'Grade 1', 'A');
    $classB = makeClass($year, 'Grade 1', 'B');

    app(SaveTimetableEntryAction::class)->execute(periodSlot([
        'year' => $year,
        'class' => $classA,
        'period' => $period,
        'teacher_id' => $teacher->id,
        'day_of_week' => 'wednesday',
    ]));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->postJson(route('academics.timetable.preview'), [
            'academic_year_id' => $year->id,
            'class_id' => $classB->id,
            'teacher_id' => $teacher->id,
            'day_of_week' => 'wednesday',
            'period_id' => $period->id,
        ])
        ->assertOk()
        ->assertJsonPath('conflicts.0.type', 'teacher');
});

it('copies a class timetable onto another class', function () {
    $admin = builderAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $source = makeClass($year, 'Grade 2', 'A');
    $target = makeClass($year, 'Grade 2', 'B');
    $period = makePeriodRow();

    app(SaveTimetableEntryAction::class)->execute(periodSlot([
        'year' => $year,
        'class' => $source,
        'period' => $period,
        'day_of_week' => 'thursday',
    ]));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.timetable.copy-from-class'), [
            'academic_year_id' => $year->id,
            'source_class_id' => $source->id,
            'target_class_id' => $target->id,
        ])
        ->assertRedirect();

    expect(Timetable::query()->where('class_id', $target->id)->count())->toBe(1)
        ->and(Timetable::query()->where('class_id', $source->id)->count())->toBe(1);
});

it('copies a week by bounding originals and shifting validity', function () {
    $admin = builderAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $period = makePeriodRow();

    $original = app(SaveTimetableEntryAction::class)->execute(periodSlot([
        'year' => $year,
        'class' => $class,
        'period' => $period,
        'day_of_week' => 'friday',
    ]));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.timetable.copy-week'), [
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'week_start' => '2026-08-24',
        ])
        ->assertRedirect();

    $original->refresh();
    $copy = Timetable::query()->where('id', '!=', $original->id)->sole();

    expect(Timetable::query()->where('class_id', $class->id)->count())->toBe(2)
        ->and($original->valid_from->toDateString())->toBe('2026-08-24')
        ->and($original->valid_until->toDateString())->toBe('2026-08-30')
        ->and($copy->valid_from->toDateString())->toBe('2026-08-31')
        ->and($copy->valid_until->toDateString())->toBe('2026-09-06')
        ->and($copy->period_id)->toBe($period->id)
        ->and($copy->day_of_week)->toBe('friday');
});

it('exports the timetable as csv', function () {
    $admin = builderAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $period = makePeriodRow();

    app(SaveTimetableEntryAction::class)->execute(periodSlot([
        'year' => $year,
        'class' => $class,
        'period' => $period,
        'day_of_week' => 'saturday',
    ]));

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.timetable.export', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain('saturday')
        ->and($csv)->toContain((string) $class->id);
});

it('forbids the builder without manage_timetables', function () {
    $admin = actingPeopleAdmin([]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.timetable.index'))
        ->assertForbidden();
});

it('rejects a conflicting store without override', function () {
    $admin = builderAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $period = makePeriodRow();
    $teacher = makeTeacherRow();
    $classA = makeClass($year, 'Grade 3', 'A');
    $classB = makeClass($year, 'Grade 3', 'B');

    app(SaveTimetableEntryAction::class)->execute(periodSlot([
        'year' => $year,
        'class' => $classA,
        'period' => $period,
        'teacher_id' => $teacher->id,
    ]));

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->from(route('academics.timetable.index', ['academic_year_id' => $year->id, 'class_id' => $classB->id]))
        ->post(route('academics.timetable.store'), [
            'academic_year_id' => $year->id,
            'class_id' => $classB->id,
            'subject_id' => makeSubject()->id,
            'teacher_id' => $teacher->id,
            'period_id' => $period->id,
            'day_of_week' => 'monday',
            'room_id' => makeRoomRow()->id,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('conflicts');
});

it('shows approved substitutions on the builder overlay', function () {
    $admin = builderAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $period = makePeriodRow();
    $absent = makeTeacherRow();
    $cover = makeTeacherRow();
    $subject = makeSubject();

    $entry = app(SaveTimetableEntryAction::class)->execute(periodSlot([
        'year' => $year,
        'class' => $class,
        'period' => $period,
        'teacher_id' => $absent->id,
        'subject_id' => $subject->id,
    ]));

    TeacherAbsence::query()->create([
        'teacher_id' => $absent->id,
        'from_date' => '2026-08-24',
        'to_date' => '2026-08-28',
        'reason' => 'Leave',
        'status' => 'approved',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);

    $request = SubstitutionRequest::query()->create([
        'timetable_entry_id' => $entry->id,
        'date' => '2026-08-24',
        'absent_teacher_id' => $absent->id,
        'subject_id' => $subject->id,
        'classroom_id' => $class->id,
        'period_id' => $period->id,
        'status' => 'open',
    ]);

    SubstitutionAssignment::query()->create([
        'substitution_request_id' => $request->id,
        'substitute_teacher_id' => $cover->id,
        'assigned_by' => $admin->id,
        'assigned_at' => now(),
    ]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.timetable.index', [
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Timetable/Builder')
            ->has('substitutions', 1)
            ->where('substitutions.0.timetable_id', $entry->id)
            ->where('substitutions.0.substitute_teacher_id', $cover->id)
        );
});
