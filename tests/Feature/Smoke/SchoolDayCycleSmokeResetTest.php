<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/school-day.mjs` adds two calendar days and marks the smoke
 * pupil late on today's register. `SmokeMarkerSeeder::schoolDayCycle()`
 * plants nothing and clears the days and the pupil's marks for today — so
 * the walk can run twice, and the seeder must too.
 */
it('clears a run\'s calendar days and the pupil\'s marks for today, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $studentId = (int) DB::table('students')->where('user_id', DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id'))->value('id');
    $yearId = (int) DB::table('academic_years')->orderBy('id')->value('id');
    $classId = (int) DB::table('students')->where('id', $studentId)->value('class_id') ?: (int) DB::table('classes')->orderBy('id')->value('id');

    // What a run leaves behind.
    $dayId = DB::table('calendar_days')->insertGetId([
        'academic_year_id' => $yearId, 'date' => now()->addWeek()->toDateString(), 'type' => 'event', 'title' => 'SMOKE-Sports-Day',
        'is_public' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $markId = DB::table('class_attendance')->insertGetId([
        'student_id' => $studentId, 'class_id' => $classId, 'academic_year_id' => $yearId, 'date' => now()->toDateString(),
        'status' => 'late', 'minutes_late' => 12, 'source' => 'register',
        'marked_by' => (int) DB::table('users')->where('email', 'teacher@akuru.edu.mv')->value('id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('calendar_days')->where('id', $dayId)->exists())->toBeFalse()
        ->and(DB::table('class_attendance')->where('id', $markId)->exists())->toBeFalse();
});
