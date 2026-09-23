<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `SmokeMarkerSeeder::calendar()` plants `SMOKE-Holiday` three days out.
 * `calendar_days` is unique on (date, year), and on a host with history that
 * date is often already taken by a walk's own entry — `create-sweep.mjs`
 * writes the day after the last one there is. Staging's fourth seed died on
 * the duplicate (STATUS §5fz); the seeder must take the date over instead.
 */
it('plants the holiday marker over a day another run already used', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);

    $yearId = (int) DB::table('academic_years')->where('status', 'active')->value('id');
    $date = now()->addDays(3)->toDateString();

    // What a create-sweep run leaves on that date, once the marker is gone.
    DB::table('calendar_days')->where('title', 'SMOKE-Holiday')->delete();
    DB::table('calendar_days')->insert([
        'academic_year_id' => $yearId, 'date' => $date, 'type' => 'event', 'title' => 'MADE0S25',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('calendar_days')->where('academic_year_id', $yearId)->where('date', $date)->pluck('title')->all())
        ->toBe(['SMOKE-Holiday']);
});
