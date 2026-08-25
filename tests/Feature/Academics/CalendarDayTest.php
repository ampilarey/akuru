<?php

use App\Domains\Academics\Actions\ListCalendarHolidaysAction;
use App\Domains\Academics\Actions\SaveCalendarDayAction;
use App\Domains\Academics\Enums\CalendarDayType;
use App\Domains\Academics\Models\CalendarDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('creates updates exports and deletes calendar days', function () {
    $admin = actingPeopleAdmin(['calendar.manage']);
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.calendar.store'), [
            'academic_year_id' => $year->id,
            'date' => '2026-08-28',
            'type' => CalendarDayType::Holiday->value,
            'title' => 'Independence Day',
            'title_arabic' => 'يوم الاستقلال',
            'title_dhivehi' => 'މިނިވަންކަން',
            'affects_timetable' => true,
        ])
        ->assertRedirect();

    $day = CalendarDay::query()->sole();
    expect($day->type)->toBe(CalendarDayType::Holiday)
        ->and($day->affects_timetable)->toBeTrue()
        ->and($day->academic_year_id)->toBe($year->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.calendar.index', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Calendar/Index')
            ->has('days', 1)
            ->where('days.0.title', 'Independence Day')
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.calendar.export', ['academic_year_id' => $year->id]))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    expect($csv)->toContain('Independence Day');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->delete(route('academics.calendar.destroy', $day))
        ->assertRedirect();

    expect(CalendarDay::query()->count())->toBe(0);
});

it('rejects a second entry on the same date and year', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);

    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $year->id,
        'date' => '2026-12-25',
        'type' => CalendarDayType::Holiday->value,
        'title' => 'Christmas',
    ]);

    expect(fn () => app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $year->id,
        'date' => '2026-12-25',
        'type' => CalendarDayType::Closure->value,
        'title' => 'Also closed',
    ]))->toThrow(ValidationException::class);
});

it('lists only holidays and closures for portal and public readers', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);

    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $year->id,
        'date' => '2026-08-28',
        'type' => CalendarDayType::Holiday->value,
        'title' => 'Independence Day',
    ]);
    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $year->id,
        'date' => '2026-09-01',
        'type' => CalendarDayType::ExamDay->value,
        'title' => 'Term exams',
        'affects_timetable' => false,
    ]);
    app(SaveCalendarDayAction::class)->execute([
        'academic_year_id' => $year->id,
        'date' => '2026-09-10',
        'type' => CalendarDayType::Closure->value,
        'title' => 'Storm closure',
    ]);

    $holidays = app(ListCalendarHolidaysAction::class)->execute($year->id);

    expect($holidays)->toHaveCount(2)
        ->and($holidays->pluck('title')->all())->toBe(['Independence Day', 'Storm closure']);

    $parent = actingPeopleAdmin([]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($parent)
        ->get(route('portal.holidays'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Holidays')
            ->has('holidays', 2)
            ->where('holidays.0.title', 'Independence Day')
        );

    $this->withoutLocalizationMiddleware()
        ->get(route('public.events.index'))
        ->assertOk()
        ->assertSee('Independence Day')
        ->assertSee('Storm closure')
        ->assertDontSee('Term exams');
});

it('forbids the admin calendar without calendar.manage', function () {
    $admin = actingPeopleAdmin([]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.calendar.index'))
        ->assertForbidden();
});
