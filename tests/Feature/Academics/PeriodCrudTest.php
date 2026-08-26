<?php

use App\Domains\Academics\Models\Period;
use Database\Seeders\PeriodSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('creates updates and exports periods', function () {
    $admin = actingPeopleAdmin(['manage_timetables']);
    makeSchool();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('academics.periods.store'), [
            'name' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '08:45',
            'order' => 1,
            'is_break' => false,
            'is_active' => true,
        ])
        ->assertRedirect(route('academics.periods.index'));

    $period = Period::query()->where('name', 'Period 1')->sole();
    expect($period->order)->toBe(1);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->put(route('academics.periods.update', $period), [
            'name' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '08:50',
            'order' => 1,
            'is_break' => false,
            'is_active' => true,
        ])
        ->assertRedirect(route('academics.periods.index'));

    expect($period->fresh()->end_time->format('H:i'))->toBe('08:50');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.periods.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Academics/Periods/Index')
            ->has('periods', 1)
            ->where('periods.0.name', 'Period 1')
            ->where('periods.0.start_time', '08:00')
        );

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.periods.export'))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Period 1');
});

it('forbids the periods screen without manage_timetables', function () {
    $admin = actingPeopleAdmin([]);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('academics.periods.index'))
        ->assertForbidden();
});

it('seeds periods from PeriodSeeder in DatabaseSeeder order', function () {
    $this->seed([
        RoleSeeder::class,
        SchoolSeeder::class,
        PeriodSeeder::class,
    ]);

    expect(Period::query()->count())->toBeGreaterThan(0)
        ->and(Period::query()->where('name', 'Period 1')->exists())->toBeTrue();
});
