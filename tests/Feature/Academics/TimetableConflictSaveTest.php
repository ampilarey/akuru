<?php

use App\Domains\Academics\Actions\BackfillTimetableYearAndRoomsAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Exceptions\TimetableConflictException;
use App\Domains\Academics\Models\Timetable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function slotPayload(array $overrides = []): array
{
    $year = $overrides['year'] ?? makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    unset($overrides['year']);

    $class = $overrides['class'] ?? makeClass($year);
    unset($overrides['class']);

    return array_merge([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => makeTeacherRow()->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'start_time' => '08:00',
        'end_time' => '08:45',
        'room_id' => makeRoomRow()->id,
    ], $overrides);
}

it('saves a non-conflicting entry and dual-writes the room name', function () {
    $room = makeRoomRow('Hall A');
    $payload = slotPayload(['room_id' => $room->id, 'start_time' => '10:00', 'end_time' => '10:45']);

    $entry = app(SaveTimetableEntryAction::class)->execute($payload);

    expect($entry->academic_year_id)->toBe($payload['academic_year_id'])
        ->and($entry->room_id)->toBe($room->id)
        ->and($entry->room)->toBe('Hall A')
        ->and($entry->period_id)->toBeNull();
});

it('rejects XOR violation when both period and times are sent', function () {
    $period = makePeriodRow();
    $payload = slotPayload([
        'period_id' => $period->id,
        'start_time' => '08:00',
        'end_time' => '08:45',
    ]);

    expect(fn () => app(SaveTimetableEntryAction::class)->execute($payload))
        ->toThrow(ValidationException::class);
});

it('copies period clock times onto a period-based save', function () {
    $period = makePeriodRow('09:00:00', '09:40:00', 2);
    $payload = slotPayload([
        'period_id' => $period->id,
        'start_time' => null,
        'end_time' => null,
        'room_id' => makeRoomRow('Studio')->id,
    ]);

    $entry = app(SaveTimetableEntryAction::class)->execute($payload);

    expect($entry->period_id)->toBe($period->id)
        ->and($entry->start_time->format('H:i'))->toBe('09:00')
        ->and($entry->end_time->format('H:i'))->toBe('09:40');
});

it('blocks a conflicting save without an override', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $teacher = makeTeacherRow();
    $room = makeRoomRow('Shared');

    $first = slotPayload([
        'year' => $year,
        'class' => $class,
        'teacher_id' => $teacher->id,
        'room_id' => $room->id,
    ]);
    app(SaveTimetableEntryAction::class)->execute($first);

    $second = slotPayload([
        'year' => $year,
        'class' => makeClass($year, 'Grade 2', 'B'),
        'teacher_id' => $teacher->id,
        'room_id' => makeRoomRow('Other')->id,
        'subject_id' => makeSubject()->id,
    ]);

    expect(fn () => app(SaveTimetableEntryAction::class)->execute($second))
        ->toThrow(TimetableConflictException::class);
});

it('saves over a conflict when permitted and logs the reason', function () {
    Event::fake([MessageLogged::class]);

    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $teacher = makeTeacherRow();
    $first = slotPayload(['year' => $year, 'teacher_id' => $teacher->id]);
    app(SaveTimetableEntryAction::class)->execute($first);

    $actor = actingPeopleAdmin(['timetables.allow_conflict']);
    $second = slotPayload([
        'year' => $year,
        'class' => makeClass($year, 'Grade 3', 'C'),
        'teacher_id' => $teacher->id,
        'room_id' => makeRoomRow('Override Room')->id,
        'subject_id' => makeSubject()->id,
        'allow_conflict' => true,
        'conflict_reason' => 'Exam week combined class',
    ]);

    $entry = app(SaveTimetableEntryAction::class)->execute($second, null, $actor->can('timetables.allow_conflict'), $actor->id);

    expect($entry->exists)->toBeTrue()
        ->and(Timetable::query()->count())->toBe(2);

    Event::assertDispatched(MessageLogged::class, fn (MessageLogged $event) => $event->message === 'timetable.conflict_override'
        && ($event->context['reason'] ?? null) === 'Exam week combined class');
});

it('rejects an override without permission or reason', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $teacher = makeTeacherRow();
    app(SaveTimetableEntryAction::class)->execute(slotPayload([
        'year' => $year,
        'teacher_id' => $teacher->id,
    ]));

    $actor = actingPeopleAdmin([]);
    $payload = slotPayload([
        'year' => $year,
        'class' => makeClass($year, 'Grade 4', 'D'),
        'teacher_id' => $teacher->id,
        'room_id' => makeRoomRow('No Override')->id,
        'subject_id' => makeSubject()->id,
        'allow_conflict' => true,
        'conflict_reason' => 'because',
    ]);

    expect(fn () => app(SaveTimetableEntryAction::class)->execute($payload, null, $actor->can('timetables.allow_conflict'), $actor->id))
        ->toThrow(ValidationException::class);

    $permitted = actingPeopleAdmin(['timetables.allow_conflict']);
    $payload['conflict_reason'] = '';

    expect(fn () => app(SaveTimetableEntryAction::class)->execute($payload, null, $permitted->can('timetables.allow_conflict'), $permitted->id))
        ->toThrow(ValidationException::class);
});

it('backfills academic_year_id and room_id from existing timetable strings', function () {
    $year = makeYear(['name' => '2025-2026', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $room = makeRoomRow('Imported Lab');

    \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
    $id = \Illuminate\Support\Facades\DB::table('timetables')->insertGetId([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => makeTeacherRow()->id,
        'day_of_week' => 'wednesday',
        'start_time' => '11:00:00',
        'end_time' => '11:45:00',
        'room' => 'Imported Lab',
        'start_date' => '2025-01-15',
        'end_date' => '2025-06-15',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

    $counts = app(BackfillTimetableYearAndRoomsAction::class)->execute();
    $countsAgain = app(BackfillTimetableYearAndRoomsAction::class)->execute();

    $row = Timetable::query()->find($id);
    expect($counts['years'])->toBe(1)
        ->and($counts['rooms'])->toBe(1)
        ->and($countsAgain['years'])->toBe(0)
        ->and($countsAgain['rooms'])->toBe(0)
        ->and($row->academic_year_id)->toBe($year->id)
        ->and($row->room_id)->toBe($room->id)
        ->and($row->valid_from->toDateString())->toBe('2025-01-15')
        ->and($row->valid_until->toDateString())->toBe('2025-06-15');
});
