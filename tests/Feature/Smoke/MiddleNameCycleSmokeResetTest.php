<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/middle-name.mjs` lends the seeded pupil a middle name on
 * their profile and takes it back (STATUS §5op). A run stopped in between
 * leaves it on; the seeder takes it off, so the walk starts from a pupil
 * with no middle name — and the seeder still runs twice.
 */
it('takes back a middle name a stopped walk left on the seeded pupil, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);

    $studentId = (int) DB::table('students')
        ->where('user_id', (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id'))
        ->value('id');
    expect($studentId)->toBeGreaterThan(0);

    DB::table('students')->where('id', $studentId)->update(['middle_name' => 'Mohamed']);

    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('students')->where('id', $studentId)->value('middle_name'))->toBeNull();
});
