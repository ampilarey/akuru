<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/timetable.mjs` places entries in `SMOKE-Class` A and B.
 * `SmokeMarkerSeeder::timetableCycle()` plants the two classes once, in the
 * active year, and clears their entries — so the walk can run twice, and
 * the seeder must too.
 */
it('plants the two smoke classes once, clears their timetable, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $classes = DB::table('classes')->where('name', 'SMOKE-Class')->orderBy('section')->get();
    $yearId = (int) DB::table('academic_years')->where('status', 'active')->value('id');

    expect($classes)->toHaveCount(2)
        ->and($classes->pluck('section')->all())->toBe(['A', 'B'])
        ->and((int) $classes->first()->academic_year_id)->toBe($yearId);

    // What a run leaves behind.
    $entryId = DB::table('timetables')->insertGetId([
        'class_id' => $classes->first()->id, 'academic_year_id' => $yearId,
        'subject_id' => (int) DB::table('subjects')->orderBy('id')->value('id'),
        'teacher_id' => (int) DB::table('teachers')->orderBy('id')->value('id'),
        'period_id' => (int) DB::table('periods')->orderBy('id')->value('id'),
        'day_of_week' => 'monday', 'start_time' => '07:45:00', 'end_time' => '08:30:00', 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('timetables')->where('id', $entryId)->exists())->toBeFalse()
        ->and(DB::table('classes')->where('name', 'SMOKE-Class')->count())->toBe(2);
});
