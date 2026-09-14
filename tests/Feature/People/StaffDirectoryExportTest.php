<?php

use App\Domains\People\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * CLAUDE.md's standing rule: *"every listing gets CSV export."*
 *
 * The student directory has had one since S1.1; the staff directory is the same
 * shape of list and never got one. Found by counting `*.index` routes against
 * `*.export` routes — 20 of 92 staff listings have no export, and most of the
 * rest are settings forms rather than lists.
 */
it('exports the staff roll as CSV', function () {
    $admin = actingPeopleAdmin(['people.manage']);

    StaffProfile::query()->create([
        'user_id' => $admin->id,
        'first_name' => 'Aishath', 'last_name' => 'Shifa',
        'gender' => 'female', 'joined_date' => '2026-01-01',
        'employment_type' => 'full_time', 'status' => 'active',
        'staff_number' => 'STF-01', 'department' => 'Quran', 'designation' => 'Teacher',
    ]);

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('people.staff.export'))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('staff_number')
        ->and($csv)->toContain('STF-01')
        ->and($csv)->toContain('Aishath')
        ->and($csv)->toContain('Quran');
});

it('keeps the national id and date of birth out of the spreadsheet', function () {
    // Both are on the screen behind a login. A spreadsheet leaves the building,
    // and a staff roll does not need either to be useful.
    $admin = actingPeopleAdmin(['people.manage']);

    StaffProfile::query()->create([
        'user_id' => $admin->id,
        'first_name' => 'Aishath', 'last_name' => 'Shifa',
        'gender' => 'female', 'joined_date' => '2026-01-01',
        'date_of_birth' => '1990-04-04',
        'national_id' => 'A123456',
        'employment_type' => 'full_time', 'status' => 'active',
    ]);

    $csv = $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('people.staff.export'))
        ->assertOk()
        ->streamedContent();

    expect($csv)->not->toContain('A123456')
        ->and($csv)->not->toContain('1990-04-04');
});
