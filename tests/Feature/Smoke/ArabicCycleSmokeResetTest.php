<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/arabic.mjs` adds the letter `smoke_letter` to the Arabic
 * reference, builds `SMOKE-Arabic-Activity` on `SMOKE-Course` tagged with
 * it, and has the student answer. `SmokeMarkerSeeder::arabicCycle()` clears
 * the attempt, the activity and the letter so the walk can run twice; the
 * seeder itself must too, and must leave the seeded reference alone.
 */
it('clears a run\'s letter, activity and attempt, keeps the seeded reference, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $courseId = (int) DB::table('courses')->where('title', 'SMOKE-Course')->value('id');
    $lettersBefore = DB::table('arabic_letters')->count();

    $letterId = DB::table('arabic_letters')->insertGetId([
        'key_name' => 'smoke_letter', 'arabic_character' => 'ڽ', 'display_name' => 'SMOKE-Letter',
        'sort_order' => 99, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $activityId = DB::table('activities')->insertGetId([
        'course_id' => $courseId, 'title' => 'SMOKE-Arabic-Activity', 'pattern' => 'selection', 'activity_type' => 'reading',
        'data' => '{}', 'settings' => json_encode(['skill' => 'reading', 'letter_id' => $letterId]), 'max_score' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('activity_attempts')->insert([
        'activity_id' => $activityId, 'course_id' => $courseId,
        'enrollment_id' => (int) DB::table('course_enrollments')->where('course_id', $courseId)->orderBy('id')->value('id'),
        'student_id' => (int) DB::table('students')->orderBy('id')->value('id'),
        'attempt_number' => 1, 'status' => 'scored', 'answers' => '{}', 'max_score' => 1, 'score' => 1,
        'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('activity_attempts')->where('activity_id', $activityId)->exists())->toBeFalse()
        ->and(DB::table('activities')->where('id', $activityId)->exists())->toBeFalse()
        ->and(DB::table('arabic_letters')->where('id', $letterId)->exists())->toBeFalse()
        ->and(DB::table('arabic_letters')->count())->toBe($lettersBefore)
        ->and(DB::table('courses')->where('id', $courseId)->exists())->toBeTrue();
});
