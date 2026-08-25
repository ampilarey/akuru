<?php

use App\Domains\Academics\Services\TimetableConflictChecker;

function entry(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'class_id' => 10,
        'teacher_id' => 20,
        'room_id' => 30,
        'day_of_week' => 'monday',
        'period_id' => null,
        'start_time' => '08:00:00',
        'end_time' => '08:45:00',
        'academic_year_id' => 1,
        'term_id' => null,
        'valid_from' => null,
        'valid_until' => null,
        'is_active' => true,
    ], $overrides);
}

function types(array $conflicts): array
{
    return array_column($conflicts, 'type');
}

$checker = new TimetableConflictChecker;
$periods = [
    1 => ['start' => '08:00', 'end' => '08:45'],
    2 => ['start' => '08:45', 'end' => '09:30'],
    3 => ['start' => '08:15', 'end' => '09:00'],
];

it('flags teacher room and class conflicts on overlapping time-based slots', function () use ($checker) {
    $existing = [entry(['id' => 5])];
    $proposed = entry(['id' => null, 'class_id' => 10, 'teacher_id' => 20, 'room_id' => 30]);

    expect(types($checker->check($proposed, $existing)))->toEqualCanonicalizing(['teacher', 'room', 'class']);
});

it('flags teacher conflict only when class and room differ', function () use ($checker) {
    $existing = [entry(['id' => 5, 'class_id' => 11, 'room_id' => 31])];
    $proposed = entry(['id' => null, 'class_id' => 10, 'teacher_id' => 20, 'room_id' => 30]);

    expect(types($checker->check($proposed, $existing)))->toBe(['teacher']);
});

it('flags room conflict only when teacher and class differ', function () use ($checker) {
    $existing = [entry(['id' => 5, 'teacher_id' => 21, 'class_id' => 11])];
    $proposed = entry(['id' => null]);

    expect(types($checker->check($proposed, $existing)))->toBe(['room']);
});

it('flags class conflict only when teacher and room differ', function () use ($checker) {
    $existing = [entry(['id' => 5, 'teacher_id' => 21, 'room_id' => 31])];
    $proposed = entry(['id' => null]);

    expect(types($checker->check($proposed, $existing)))->toBe(['class']);
});

it('does not conflict when times touch but do not overlap', function () use ($checker) {
    $existing = [entry(['id' => 5, 'start_time' => '08:00:00', 'end_time' => '08:45:00'])];
    $proposed = entry(['id' => null, 'start_time' => '08:45:00', 'end_time' => '09:30:00']);

    expect($checker->check($proposed, $existing))->toBe([]);
});

it('conflicts when time-based windows overlap', function () use ($checker) {
    $existing = [entry(['id' => 5, 'start_time' => '08:00:00', 'end_time' => '08:45:00'])];
    $proposed = entry(['id' => null, 'start_time' => '08:30:00', 'end_time' => '09:15:00']);

    expect(types($checker->check($proposed, $existing)))->not->toBeEmpty();
});

it('uses period times for period-based entries', function () use ($checker, $periods) {
    $existing = [entry(['id' => 5, 'period_id' => 1, 'start_time' => null, 'end_time' => null])];
    $same = entry(['id' => null, 'period_id' => 1, 'start_time' => null, 'end_time' => null]);
    $adjacent = entry(['id' => null, 'period_id' => 2, 'start_time' => null, 'end_time' => null]);

    expect(types($checker->check($same, $existing, $periods)))->not->toBeEmpty()
        ->and($checker->check($adjacent, $existing, $periods))->toBe([]);
});

it('detects overlap between a period slot and a time-based slot', function () use ($checker, $periods) {
    $existing = [entry(['id' => 5, 'period_id' => 1, 'start_time' => null, 'end_time' => null])];
    $proposed = entry(['id' => null, 'period_id' => null, 'start_time' => '08:15:00', 'end_time' => '09:00:00']);

    expect(types($checker->check($proposed, $existing, $periods)))->not->toBeEmpty();
});

it('ignores a different day', function () use ($checker) {
    $existing = [entry(['id' => 5, 'day_of_week' => 'monday'])];
    $proposed = entry(['id' => null, 'day_of_week' => 'tuesday']);

    expect($checker->check($proposed, $existing))->toBe([]);
});

it('ignores a different academic year', function () use ($checker) {
    $existing = [entry(['id' => 5, 'academic_year_id' => 1])];
    $proposed = entry(['id' => null, 'academic_year_id' => 2]);

    expect($checker->check($proposed, $existing))->toBe([]);
});

it('does not conflict across distinct terms', function () use ($checker) {
    $existing = [entry(['id' => 5, 'term_id' => 1])];
    $proposed = entry(['id' => null, 'term_id' => 2]);

    expect($checker->check($proposed, $existing))->toBe([]);
});

it('conflicts when one entry is whole-year and the other is term-scoped', function () use ($checker) {
    $existing = [entry(['id' => 5, 'term_id' => null])];
    $proposed = entry(['id' => null, 'term_id' => 1]);

    expect(types($checker->check($proposed, $existing)))->not->toBeEmpty();
});

it('does not conflict when validity windows do not overlap', function () use ($checker) {
    $existing = [entry(['id' => 5, 'valid_from' => '2026-01-01', 'valid_until' => '2026-03-31'])];
    $proposed = entry(['id' => null, 'valid_from' => '2026-04-01', 'valid_until' => '2026-06-30']);

    expect($checker->check($proposed, $existing))->toBe([]);
});

it('conflicts when validity windows overlap', function () use ($checker) {
    $existing = [entry(['id' => 5, 'valid_from' => '2026-01-01', 'valid_until' => '2026-06-30'])];
    $proposed = entry(['id' => null, 'valid_from' => '2026-06-01', 'valid_until' => '2026-12-31']);

    expect(types($checker->check($proposed, $existing)))->not->toBeEmpty();
});

it('treats a null validity bound as unbounded', function () use ($checker) {
    $existing = [entry(['id' => 5, 'valid_from' => null, 'valid_until' => null])];
    $proposed = entry(['id' => null, 'valid_from' => '2026-06-01', 'valid_until' => '2026-06-30']);

    expect(types($checker->check($proposed, $existing)))->not->toBeEmpty();
});

it('ignores inactive existing rows and the same timetable id', function () use ($checker) {
    $inactive = [entry(['id' => 5, 'is_active' => false])];
    $self = [entry(['id' => 9])];

    expect($checker->check(entry(['id' => null]), $inactive))->toBe([])
        ->and($checker->check(entry(['id' => 9]), $self))->toBe([]);
});

it('does not room-conflict when either room_id is empty', function () use ($checker) {
    $existing = [entry(['id' => 5, 'room_id' => null, 'teacher_id' => 21, 'class_id' => 11])];
    $proposed = entry(['id' => null, 'room_id' => 30, 'teacher_id' => 22, 'class_id' => 12]);

    expect($checker->check($proposed, $existing))->toBe([]);
});
