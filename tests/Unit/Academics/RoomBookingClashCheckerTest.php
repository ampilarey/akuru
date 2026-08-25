<?php

use App\Domains\Academics\Services\RoomBookingClashChecker;

function bookingSlot(array $overrides = []): array
{
    return array_merge([
        'id' => 10,
        'room_id' => 1,
        'academic_year_id' => 1,
        'date' => '2026-08-24',
        'day_of_week' => 'monday',
        'period_id' => null,
        'start_time' => '08:00:00',
        'end_time' => '08:45:00',
    ], $overrides);
}

function timetableSlot(array $overrides = []): array
{
    return array_merge([
        'id' => 20,
        'room_id' => 1,
        'academic_year_id' => 1,
        'day_of_week' => 'monday',
        'period_id' => null,
        'start_time' => '08:00:00',
        'end_time' => '08:45:00',
        'valid_from' => null,
        'valid_until' => null,
        'is_active' => true,
    ], $overrides);
}

it('flags a booking that overlaps another booking in the same room', function () {
    $checker = new RoomBookingClashChecker;

    $conflicts = $checker->checkBooking(
        bookingSlot(['id' => 11, 'start_time' => '08:30:00', 'end_time' => '09:00:00']),
        [bookingSlot()],
        [],
    );

    expect($conflicts)->toBe([['type' => 'booking', 'id' => 10]]);
});

it('does not clash when booking times only touch', function () {
    $checker = new RoomBookingClashChecker;

    expect($checker->checkBooking(
        bookingSlot(['id' => 11, 'start_time' => '08:45:00', 'end_time' => '09:30:00']),
        [bookingSlot()],
        [],
    ))->toBe([]);
});

it('flags a booking that overlaps a timetable room slot', function () {
    $checker = new RoomBookingClashChecker;

    $conflicts = $checker->checkBooking(
        bookingSlot(['id' => 11]),
        [],
        [timetableSlot()],
    );

    expect($conflicts)->toBe([['type' => 'timetable', 'id' => 20]]);
});

it('ignores a timetable slot outside the booking date window', function () {
    $checker = new RoomBookingClashChecker;

    expect($checker->checkBooking(
        bookingSlot(['date' => '2026-09-07', 'day_of_week' => 'monday']),
        [],
        [timetableSlot(['valid_from' => '2026-08-24', 'valid_until' => '2026-08-30'])],
    ))->toBe([]);
});

it('ignores a different room or day', function () {
    $checker = new RoomBookingClashChecker;

    expect($checker->checkBooking(
        bookingSlot(['room_id' => 2]),
        [],
        [timetableSlot()],
    ))->toBe([])
        ->and($checker->checkBooking(
            bookingSlot(['day_of_week' => 'tuesday', 'date' => '2026-08-25']),
            [],
            [timetableSlot()],
        ))->toBe([]);
});

it('flags a timetable save that overlaps an existing booking', function () {
    $checker = new RoomBookingClashChecker;

    expect($checker->checkTimetable(
        timetableSlot(),
        [bookingSlot()],
    ))->toBe([['type' => 'booking', 'id' => 10]]);
});

it('does not timetable-clash when the room is empty', function () {
    $checker = new RoomBookingClashChecker;

    expect($checker->checkTimetable(
        timetableSlot(['room_id' => null]),
        [bookingSlot()],
    ))->toBe([]);
});
