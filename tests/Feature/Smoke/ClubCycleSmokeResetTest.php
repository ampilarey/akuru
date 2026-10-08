<?php

use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/course-screens-language.mjs` opens the clubs, a club's
 * roster and its sheet (STATUS §5ou), and nothing seeded a club. The seeder
 * plants `SMOKE-Club` with the seeded pupil on its roster — once, however
 * often it runs.
 */
it('plants one smoke club with the seeded pupil on its roster, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $clubs = DB::table('courses')->where('slug', 'smoke-club')->get();
    expect($clubs)->toHaveCount(1)
        ->and($clubs->first()->course_type)->toBe('club');

    $pupilId = (int) DB::table('students')
        ->where('user_id', (int) DB::table('users')->where('email', 'student@akuru.edu.mv')->value('id'))
        ->value('id');
    expect(DB::table('course_enrollments')->where('course_id', $clubs->first()->id)->where('unified_student_id', $pupilId)->whereNull('deleted_at')->count())->toBe(1)
        ->and(DB::table('course_offerings')->where('course_id', $clubs->first()->id)->where('delivery_mode', 'self_learning')->count())->toBe(1);
});
