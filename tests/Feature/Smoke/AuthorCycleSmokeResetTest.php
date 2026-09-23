<?php

use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use Database\Seeders\SmokeMarkerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `scripts/smoke/author.mjs` builds `SMOKE-Authored` through the screens and
 * leaves a course, an offering, a module, a lesson with a revision, an
 * enrolment and progress behind. `SmokeMarkerSeeder::authorCycle()` clears
 * all of it so the walk can run twice; the seeder itself must run twice.
 */
it('clears everything an author walk leaves behind, and runs twice', function () {
    $this->seed();
    $this->seed(SmokeMarkerSeeder::class);
    $this->seed(SmokeMarkerSeeder::class);

    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute(['title' => 'SMOKE-Authored', 'created_by' => $admin->id]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $moduleId = DB::table('course_modules')->insertGetId([
        'course_id' => $course->id, 'title' => 'SMOKE-Authored-Module', 'position' => 1, 'status' => 'published',
        'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $lessonId = DB::table('lessons')->insertGetId([
        'course_id' => $course->id, 'course_module_id' => $moduleId, 'title' => 'SMOKE-Authored-Lesson',
        'slug' => 'smoke-authored-lesson', 'position' => 1, 'status' => 'draft', 'created_by' => $admin->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(DB::table('course_offerings')->where('course_id', $course->id)->exists())->toBeTrue();

    $this->seed(SmokeMarkerSeeder::class);

    expect(DB::table('courses')->where('title', 'SMOKE-Authored')->exists())->toBeFalse()
        ->and(DB::table('course_offerings')->where('course_id', $course->id)->exists())->toBeFalse()
        ->and(DB::table('course_modules')->where('id', $moduleId)->exists())->toBeFalse()
        ->and(DB::table('lessons')->where('id', $lessonId)->exists())->toBeFalse()
        // The seeder's own course is untouched.
        ->and(DB::table('courses')->where('title', 'SMOKE-Course')->exists())->toBeTrue();
});
