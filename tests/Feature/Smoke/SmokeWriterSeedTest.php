<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * BP4 (STATUS §5tt): the Library walks write as their own adult,
 * `smoke-writer@`, not the seeded pupil, so the pupil is never left a writer
 * for the blog walks after them. The seeder makes them once, signed-in ready,
 * with no role and no writer profile, and still runs twice.
 */
it('seeds the walks\' own writer once, with no role and no writer profile, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $writers = DB::table('users')->where('email', 'smoke-writer@akuru.edu.mv')->get();
    expect($writers)->toHaveCount(1)->and($writers[0]->email_verified_at)->not->toBeNull();
    expect(DB::table('model_has_roles')->where('model_id', $writers[0]->id)->count())->toBe(0)
        ->and(DB::table('writer_profiles')->where('user_id', $writers[0]->id)->exists())->toBeFalse();
    // The pupil the blog walks use is no writer either.
    $pupil = (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id');
    expect(DB::table('writer_profiles')->where('user_id', $pupil)->exists())->toBeFalse();
});
