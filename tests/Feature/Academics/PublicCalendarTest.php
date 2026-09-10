<?php

use App\Domains\Academics\Actions\ListCalendarHolidaysAction;
use App\Domains\Academics\Actions\ListPublicCalendarAction;
use App\Domains\Academics\Actions\SaveCalendarDayAction;
use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\Academics\Models\CalendarDay;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * E11b — the school calendar families and teachers can actually see.
 *
 * Five entry types have been recordable since the calendar shipped; the portal
 * read returned two of them, so a sports day or an exam week was entered by the
 * office and read by nobody. The fix needed an audience, not a wider whitelist.
 */
function calendarYear(): \App\Domains\Academics\Models\AcademicYear
{
    return makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
}

function calendarDay(int $yearId, string $date, string $type, array $overrides = []): CalendarDay
{
    return app(SaveCalendarDayAction::class)->execute(array_merge([
        'academic_year_id' => $yearId,
        'date' => $date,
        'type' => $type,
        'title' => ucfirst(str_replace('_', ' ', $type)).' '.$date,
    ], $overrides));
}

it('publishes a closed day without being asked', function () {
    $year = calendarYear();

    // A family that is not told the school is shut turns up at the gate.
    expect(calendarDay((int) $year->id, '2026-11-01', CalendarDayType::Holiday->value)->is_public)->toBeTrue()
        ->and(calendarDay((int) $year->id, '2026-11-02', CalendarDayType::Closure->value)->is_public)->toBeTrue();
});

it('keeps anything else to the office until somebody says otherwise', function () {
    $year = calendarYear();

    // Absent means "not decided", which for an audience must read as no.
    expect(calendarDay((int) $year->id, '2026-11-03', CalendarDayType::Event->value)->is_public)->toBeFalse()
        ->and(calendarDay((int) $year->id, '2026-11-04', CalendarDayType::ExamDay->value)->is_public)->toBeFalse()
        ->and(calendarDay((int) $year->id, '2026-11-05', CalendarDayType::SpecialSchedule->value)->is_public)->toBeFalse();
});

it('shows every published type, not only holidays', function () {
    $year = calendarYear();
    calendarDay((int) $year->id, '2036-11-01', CalendarDayType::Holiday->value);
    calendarDay((int) $year->id, '2036-11-03', CalendarDayType::Event->value, ['is_public' => true]);
    calendarDay((int) $year->id, '2036-11-04', CalendarDayType::ExamDay->value, ['is_public' => true]);
    // Entered by the office and nobody's business outside it.
    calendarDay((int) $year->id, '2036-11-05', CalendarDayType::Event->value);

    $rows = app(ListPublicCalendarAction::class)->execute((int) $year->id)['upcoming'];

    expect(collect($rows)->pluck('type')->all())->toBe(['holiday', 'event', 'exam_day']);
});

it('says plainly whether there is school', function () {
    $year = calendarYear();
    calendarDay((int) $year->id, '2036-11-01', CalendarDayType::Holiday->value);
    calendarDay((int) $year->id, '2036-11-03', CalendarDayType::Event->value, [
        'is_public' => true,
        'affects_timetable' => false,
    ]);

    $rows = collect(app(ListPublicCalendarAction::class)->execute((int) $year->id)['upcoming'])->keyBy('type');

    // "special_schedule" tells a parent nothing about whether to send their
    // child in; this does.
    expect($rows['holiday']['no_school'])->toBeTrue()
        ->and($rows['event']['no_school'])->toBeFalse();
});

it('never publishes the offices own notes', function () {
    $year = calendarYear();
    calendarDay((int) $year->id, '2036-11-01', CalendarDayType::Event->value, [
        'is_public' => true,
        'notes' => 'Ring the caterer, last year they were late',
    ]);

    // Publishing the row must not publish the margin.
    expect(app(ListPublicCalendarAction::class)->execute((int) $year->id)['upcoming'][0])
        ->not->toHaveKey('notes');
});

it('splits what is coming from what has gone', function () {
    $year = calendarYear();
    calendarDay((int) $year->id, '2020-01-06', CalendarDayType::Holiday->value);
    calendarDay((int) $year->id, '2020-01-07', CalendarDayType::Holiday->value);
    calendarDay((int) $year->id, '2036-11-01', CalendarDayType::Holiday->value);

    $payload = app(ListPublicCalendarAction::class)->execute((int) $year->id);

    expect($payload['upcoming'])->toHaveCount(1)
        ->and($payload['past'])->toHaveCount(2)
        // Newest first: last week matters more than last August.
        ->and($payload['past'][0]['date'])->toBe('2020-01-07');
});

it('leaves the staff-attendance holiday read alone', function () {
    $year = calendarYear();
    calendarDay((int) $year->id, '2036-11-01', CalendarDayType::Holiday->value);
    calendarDay((int) $year->id, '2036-11-03', CalendarDayType::Event->value, ['is_public' => true]);

    // HR reads this to decide which days staff are not expected in. Broadening
    // it would have marked every teacher on holiday for a sports day.
    expect(app(ListCalendarHolidaysAction::class)->execute((int) $year->id)->pluck('type')->all())
        ->toBe(['holiday']);
});

it('lets any signed-in person read the calendar', function () {
    $year = calendarYear();
    calendarDay((int) $year->id, '2036-11-03', CalendarDayType::Event->value, ['is_public' => true]);

    // No permission needed: this is the school's own calendar, and a parent
    // holds no permissions at all.
    $this->withoutLocalizationMiddleware()
        ->actingAs(User::factory()->create())
        ->get(route('portal.holidays'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/SchoolCalendar')
            ->has('upcoming', 1)
            ->where('upcoming.0.type', 'event')
        );
});

it('keeps the calendar away from anonymous visitors', function () {
    $year = calendarYear();
    calendarDay((int) $year->id, '2036-11-03', CalendarDayType::Event->value, ['is_public' => true]);

    // Its own test rather than a second request in the one above: actingAs
    // persists for the rest of a test, so an anonymous assertion tacked on
    // after a signed-in one is not anonymous at all and passes for free.
    $this->withoutLocalizationMiddleware()
        ->get(route('portal.holidays'))
        ->assertRedirect();
});

it('saves the audience from the admin screen', function () {
    $year = calendarYear();
    $admin = actingPeopleAdmin(['calendar.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.calendar.store'), [
            'academic_year_id' => $year->id,
            'date' => '2036-11-03',
            'type' => CalendarDayType::Event->value,
            'title' => 'Sports day',
            'affects_timetable' => false,
            'is_public' => true,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(CalendarDay::query()->sole()->is_public)->toBeTrue();
});
