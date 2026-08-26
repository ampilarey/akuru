<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects a duplicate academic year name with a validation error', function () {
    $admin = actingPeopleAdmin();
    makeYear(['name' => 'Extra']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->from(route('academics.years.index'))
        ->post(route('academics.years.store'), [
            'name' => 'Extra',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('name');
});

it('rejects a duplicate class name and section for the same year', function () {
    $admin = actingPeopleAdmin();
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    makeClass($year, 'Grade 5', 'B');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->from(route('academics.classes.index', ['academic_year_id' => $year->id]))
        ->post(route('academics.classes.store'), [
            'academic_year_id' => $year->id,
            'name' => 'Grade 5',
            'section' => 'B',
            'level' => 'Primary',
            'capacity' => 20,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('name');
});
