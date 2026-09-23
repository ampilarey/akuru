<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/assess.mjs` writes `SMOKE-Q1` and `SMOKE-Q2` into the bank,
 * builds `SMOKE-Assessment` on `SMOKE-Course`, attaches both and has the
 * student sit it. `SmokeMarkerSeeder::assessCycle()` clears all of it so
 * the walk can run twice; the seeder itself must too.
 */
it('clears a run\'s questions, assessment, pivot and attempts, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $courseId = (int) DB::table('courses')->where('title', 'SMOKE-Course')->value('id');
    $questionId = DB::table('questions')->insertGetId([
        'question_type' => 'short_answer', 'pattern' => 'text_input', 'question_text' => 'SMOKE-Q2: left over', 'difficulty' => 'medium',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $assessmentId = DB::table('assessments')->insertGetId([
        'course_id' => $courseId, 'title' => 'SMOKE-Assessment', 'status' => 'published', 'max_score' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('assessment_questions')->insert([
        'assessment_id' => $assessmentId, 'question_id' => $questionId, 'position' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('assessment_attempts')->insert([
        'assessment_id' => $assessmentId, 'course_id' => $courseId,
        'enrollment_id' => (int) DB::table('course_enrollments')->where('course_id', $courseId)->orderBy('id')->value('id'),
        'student_id' => (int) DB::table('students')->orderBy('id')->value('id'),
        'attempt_number' => 1, 'status' => 'scored', 'answers' => '{}', 'snapshots' => '[]', 'max_score' => 1, 'score' => 1,
        'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('assessment_attempts')->where('assessment_id', $assessmentId)->exists())->toBeFalse()
        ->and(DB::table('assessment_questions')->where('assessment_id', $assessmentId)->exists())->toBeFalse()
        ->and(DB::table('assessments')->where('id', $assessmentId)->exists())->toBeFalse()
        ->and(DB::table('questions')->where('id', $questionId)->exists())->toBeFalse()
        ->and(DB::table('courses')->where('id', $courseId)->exists())->toBeTrue();
});
