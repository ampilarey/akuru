<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/quran.mjs` builds `SMOKE-Recite-Activity` on `SMOKE-Course`,
 * has the student hand it in, and links `SMOKE-Offering` to the seeded
 * `SMOKE-Halaqa` program with one of its sessions mapped.
 * `SmokeMarkerSeeder::quranCycle()` clears the activity and its attempts and
 * re-plants the program, its session and no links — so the walk can run
 * twice, and the seeder must too.
 */
it('plants one Hifz program with a session, clears a run\'s activity, attempt and links, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $courseId = (int) DB::table('courses')->where('title', 'SMOKE-Course')->value('id');
    $offeringId = (int) DB::table('course_offerings')->where('title', 'SMOKE-Offering')->value('id');
    $programId = (int) DB::table('hifz_programs')->where('name', 'SMOKE-Halaqa')->value('id');

    expect(DB::table('hifz_programs')->where('name', 'SMOKE-Halaqa')->count())->toBe(1)
        ->and(DB::table('hifz_sessions')->where('hifz_program_id', $programId)->count())->toBe(1);

    // What a run leaves behind.
    $activityId = DB::table('activities')->insertGetId([
        'course_id' => $courseId, 'title' => 'SMOKE-Recite-Activity', 'pattern' => 'teacher_marked', 'activity_type' => 'recitation',
        'data' => '{}', 'settings' => json_encode(['surah_id' => 1, 'ayah_start' => 1, 'ayah_end' => 2]), 'max_score' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('activity_attempts')->insert([
        'activity_id' => $activityId, 'course_id' => $courseId,
        'enrollment_id' => (int) DB::table('course_enrollments')->where('course_id', $courseId)->orderBy('id')->value('id'),
        'student_id' => (int) DB::table('students')->orderBy('id')->value('id'),
        'attempt_number' => 1, 'status' => 'submitted', 'answers' => '{}', 'max_score' => 1,
        'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $sessionId = DB::table('course_offering_sessions')->insertGetId([
        'course_offering_id' => $offeringId, 'title' => 'SMOKE-Halaqa-Session', 'session_type' => 'face_to_face',
        'starts_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('offering_halaqa_links')->insert([
        'course_offering_id' => $offeringId, 'hifz_program_id' => $programId, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('offering_halaqa_session_links')->insert([
        'course_offering_session_id' => $sessionId,
        'hifz_session_id' => (int) DB::table('hifz_sessions')->where('hifz_program_id', $programId)->value('id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    $newProgramId = (int) DB::table('hifz_programs')->where('name', 'SMOKE-Halaqa')->value('id');

    expect(DB::table('activity_attempts')->where('activity_id', $activityId)->exists())->toBeFalse()
        ->and(DB::table('activities')->where('id', $activityId)->exists())->toBeFalse()
        ->and(DB::table('hifz_programs')->where('id', $programId)->exists())->toBeFalse()
        ->and(DB::table('hifz_programs')->where('name', 'SMOKE-Halaqa')->count())->toBe(1)
        ->and(DB::table('hifz_sessions')->where('hifz_program_id', $newProgramId)->count())->toBe(1)
        ->and(DB::table('offering_halaqa_links')->where('hifz_program_id', $programId)->exists())->toBeFalse()
        ->and(DB::table('offering_halaqa_session_links')->where('course_offering_session_id', $sessionId)->exists())->toBeFalse();
});
