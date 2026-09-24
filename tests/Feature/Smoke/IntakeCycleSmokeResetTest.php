<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/intake.mjs` creates `SMOKE-Intake` on the seeded
 * `SMOKE-Intake-Course`, a session on it, an enrolment into it and an
 * attendance mark. `SmokeMarkerSeeder::intakeCycle()` keeps the course and
 * clears the rest, so the walk can run twice; the seeder itself must too.
 */
it('keeps the intake course, clears a run\'s offering, session, attendance and enrolment, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $courseId = (int) DB::table('courses')->where('slug', 'smoke-intake-course')->value('id');
    expect($courseId)->toBeGreaterThan(0)
        ->and(DB::table('courses')->where('slug', 'smoke-intake-course')->count())->toBe(1)
        ->and(DB::table('courses')->where('id', $courseId)->value('workflow_status'))->toBe('published');

    // What a run leaves behind.
    $offeringId = DB::table('course_offerings')->insertGetId([
        'course_id' => $courseId, 'title' => 'SMOKE-Intake', 'slug' => 'smoke-intake', 'delivery_mode' => 'face_to_face',
        'status' => 'open', 'seat_limit' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $sessionId = DB::table('course_offering_sessions')->insertGetId([
        'course_offering_id' => $offeringId, 'title' => 'SMOKE-Session', 'session_type' => 'face_to_face',
        'starts_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $studentId = (int) DB::table('students')->orderBy('id')->value('id');
    $enrollmentId = DB::table('course_enrollments')->insertGetId([
        'course_id' => $courseId, 'course_offering_id' => $offeringId, 'unified_student_id' => $studentId,
        'status' => 'active', 'enrolled_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('attendance_records')->insert([
        'course_offering_session_id' => $sessionId, 'course_offering_id' => $offeringId, 'enrollment_id' => $enrollmentId,
        'student_id' => $studentId, 'status' => 'present', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('course_offerings')->where('id', $offeringId)->exists())->toBeFalse()
        ->and(DB::table('course_offering_sessions')->where('id', $sessionId)->exists())->toBeFalse()
        ->and(DB::table('attendance_records')->where('course_offering_id', $offeringId)->exists())->toBeFalse()
        ->and(DB::table('course_enrollments')->where('id', $enrollmentId)->exists())->toBeFalse()
        ->and(DB::table('courses')->where('id', $courseId)->exists())->toBeTrue();
});
