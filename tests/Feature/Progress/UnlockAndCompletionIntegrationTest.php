<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\StartOrCompleteLessonProgressAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Offerings\Actions\RecordOfferingAttendanceAction;
use App\Domains\Offerings\Actions\SaveOfferingSessionAction;
use App\Domains\Offerings\Models\CourseOffering;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes required session attendance in course completion', function () {
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Unlock Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'One',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Only lesson',
        'created_by' => $admin->id,
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Body'],
        'created_by' => $admin->id,
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    $offering = CourseOffering::query()->where('course_id', $course->id)->firstOrFail();
    $session = app(SaveOfferingSessionAction::class)->execute([
        'course_offering_id' => $offering->id,
        'title' => 'Required live',
        'session_type' => 'live_online',
        'starts_at' => now()->addDay(),
        'is_required' => true,
    ]);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Nadira']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $course->id, $offering->id);

    app(StartOrCompleteLessonProgressAction::class)->execute($lesson->id, $user, 'completed');
    expect(CourseEnrollment::query()->find($enrollment->id)?->progress_percentage)->toBe(50);

    app(RecordOfferingAttendanceAction::class)->execute([
        'course_offering_session_id' => $session->id,
        'enrollment_id' => $enrollment->id,
        'status' => 'present',
        'attendance_mode' => 'online',
        'marked_by' => $admin->id,
    ]);

    $fresh = CourseEnrollment::query()->find($enrollment->id);
    expect($fresh?->progress_percentage)->toBe(100)
        ->and($fresh?->status)->toBe('completed');
});
