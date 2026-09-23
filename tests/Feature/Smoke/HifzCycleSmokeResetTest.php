<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/hifz.mjs` enrols the smoke pupil in `SMOKE-Halaqa` and
 * takes the seeded `SMOKE-Milestone` from pending to approved.
 * `SmokeMarkerSeeder::hifzCycle()` gives the programme the supervisor and
 * teacher the walk signs in as and re-plants the milestone pending; the
 * enrolment goes with the programme `quranCycle()` re-plants — so the
 * walk can run twice, and the seeder must too.
 */
it('gives the programme its supervisor and teacher, re-plants the milestone pending, clears the enrolment, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $program = DB::table('hifz_programs')->where('name', 'SMOKE-Halaqa')->first();
    $supervisorId = (int) DB::table('users')->where('email', 'supervisor@akuru.edu.mv')->value('id');
    $studentId = (int) DB::table('students')->where('user_id', DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id'))->value('id');

    expect((int) $program->supervisor_id)->toBe($supervisorId)
        ->and((int) $program->default_teacher_id)->toBeGreaterThan(0)
        ->and(DB::table('hifz_milestones')->where('title', 'SMOKE-Milestone')->count())->toBe(1);

    // What a run leaves behind.
    DB::table('hifz_milestones')->where('title', 'SMOKE-Milestone')->update(['status' => 'approved', 'approved_at' => now()]);
    $enrollmentId = DB::table('hifz_enrollments')->insertGetId([
        'hifz_program_id' => $program->id, 'student_id' => $studentId, 'teacher_id' => $program->default_teacher_id,
        'start_date' => now()->toDateString(), 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('hifz_enrollments')->where('id', $enrollmentId)->exists())->toBeFalse()
        ->and(DB::table('hifz_milestones')->where('title', 'SMOKE-Milestone')->count())->toBe(1)
        ->and(DB::table('hifz_milestones')->where('title', 'SMOKE-Milestone')->value('status'))->toBe('pending')
        ->and((int) DB::table('hifz_milestones')->where('title', 'SMOKE-Milestone')->value('student_id'))->toBe($studentId);
});
