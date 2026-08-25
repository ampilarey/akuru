<?php

use App\Domains\Academics\Actions\SaveRoomBookingAction;
use App\Domains\Academics\Actions\SaveTimetableEntryAction;
use App\Domains\Academics\Exceptions\RoomBookingClashException;
use App\Domains\Academics\Exceptions\TimetableConflictException;
use App\Domains\Academics\Models\RoomBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function bookingAdmin(): \App\Domains\Identity\Models\User
{
    return actingPeopleAdmin(['rooms.manage']);
}

function bookingPayload(array $overrides = []): array
{
    $year = $overrides['year'] ?? makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    unset($overrides['year']);

    $roomId = $overrides['room_id'] ?? makeRoomRow()->id;

    return array_merge([
        'academic_year_id' => $year->id,
        'room_id' => $roomId,
        'title' => 'Parents evening',
        'date' => '2026-08-24',
        'start_time' => '18:00',
        'end_time' => '20:00',
    ], $overrides);
}

it('creates updates exports and deletes a room booking', function () {
    $admin = bookingAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $room = makeRoomRow('Hall A');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.bookings.store'), [
            'academic_year_id' => $year->id,
            'room_id' => $room->id,
            'title' => 'Parents evening',
            'title_arabic' => 'أمسية',
            'title_dhivehi' => 'ވަޅު',
            'date' => '2026-08-24',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'notes' => 'Hall setup at 17:30',
        ])
        ->assertRedirect();

    $booking = RoomBooking::query()->sole();
    expect($booking->title)->toBe('Parents evening')
        ->and($booking->room_id)->toBe($room->id)
        ->and($booking->academic_year_id)->toBe($year->id)
        ->and($booking->booked_by)->toBe($admin->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->put(route('academics.bookings.update', $booking), [
            'academic_year_id' => $year->id,
            'room_id' => $room->id,
            'title' => 'Parents evening (updated)',
            'date' => '2026-08-24',
            'start_time' => '18:00',
            'end_time' => '20:30',
        ])
        ->assertRedirect();

    expect($booking->fresh()->title)->toBe('Parents evening (updated)')
        ->and($booking->fresh()->end_time->format('H:i'))->toBe('20:30');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.bookings.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Bookings/Index')
            ->has('bookings', 1)
            ->where('bookings.0.title', 'Parents evening (updated)')
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.bookings.export', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain('Parents evening (updated)');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->delete(route('academics.bookings.destroy', $booking))
        ->assertRedirect();

    expect(RoomBooking::query()->count())->toBe(0);
});

it('rejects a booking that clashes with another booking', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $room = makeRoomRow('Shared Hall');

    app(SaveRoomBookingAction::class)->execute(bookingPayload([
        'year' => $year,
        'room_id' => $room->id,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]));

    expect(fn () => app(SaveRoomBookingAction::class)->execute(bookingPayload([
        'year' => $year,
        'room_id' => $room->id,
        'title' => 'Overlap',
        'start_time' => '10:30',
        'end_time' => '11:30',
    ])))->toThrow(RoomBookingClashException::class);
});

it('rejects a booking that clashes with a timetable slot', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $room = makeRoomRow('Science Lab');
    $period = makePeriodRow('08:00:00', '08:45:00');

    app(SaveTimetableEntryAction::class)->execute([
        'class_id' => $class->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => makeTeacherRow()->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'period_id' => $period->id,
        'room_id' => $room->id,
    ]);

    expect(fn () => app(SaveRoomBookingAction::class)->execute(bookingPayload([
        'year' => $year,
        'room_id' => $room->id,
        'date' => '2026-08-24',
        'period_id' => $period->id,
        'start_time' => null,
        'end_time' => null,
    ])))->toThrow(RoomBookingClashException::class);
});

it('blocks a timetable save over an existing booking', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $room = makeRoomRow('Blocked Hall');

    app(SaveRoomBookingAction::class)->execute(bookingPayload([
        'year' => $year,
        'room_id' => $room->id,
        'date' => '2026-08-24',
        'start_time' => '08:00',
        'end_time' => '08:45',
    ]));

    expect(fn () => app(SaveTimetableEntryAction::class)->execute([
        'class_id' => makeClass($year)->id,
        'subject_id' => makeSubject()->id,
        'teacher_id' => makeTeacherRow()->id,
        'academic_year_id' => $year->id,
        'day_of_week' => 'monday',
        'start_time' => '08:00',
        'end_time' => '08:45',
        'room_id' => $room->id,
    ]))->toThrow(TimetableConflictException::class);
});

it('forbids bookings without rooms.manage', function () {
    $admin = actingPeopleAdmin([]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.bookings.index'))
        ->assertForbidden();
});

it('rejects a booking on a non-bookable room', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $room = makeRoomRow('Storage');
    $room->bookable = false;
    $room->save();

    expect(fn () => app(SaveRoomBookingAction::class)->execute(bookingPayload([
        'year' => $year,
        'room_id' => $room->id,
    ])))->toThrow(\Illuminate\Validation\ValidationException::class);
});
