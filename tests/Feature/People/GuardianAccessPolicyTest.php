<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\People\Actions\AttachGuardianAction;
use App\Domains\People\Actions\GuardianCanAccessStudentAction;
use App\Domains\People\Enums\GuardianRelationship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('allows a linked guardian to view child learning and denies strangers', function () {
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Parent Watch',
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
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
        'title' => 'Open',
        'created_by' => $admin->id,
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Hello'],
        'created_by' => $admin->id,
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    $studentUser = User::factory()->create();
    $student = makeStudent(['user_id' => $studentUser->id]);
    app(EnrollSelfLearningAction::class)->execute($studentUser->id, $course->id);

    $guardian = makeGuardian();
    app(AttachGuardianAction::class)->execute($student, $guardian, GuardianRelationship::Father, true);

    expect(app(GuardianCanAccessStudentAction::class)->execute((int) $guardian->user_id, $student->id))->toBeTrue()
        ->and(app(GuardianCanAccessStudentAction::class)->execute((int) User::factory()->create()->id, $student->id))->toBeFalse();

    $guardianUser = User::query()->findOrFail($guardian->user_id);
    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('portal.learning'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Portal/Learning')
            ->has('children', 1)
            ->where('children.0.enrollments.0.progress_percentage', 0)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($guardianUser)
        ->get(route('learn.lessons.show', $lesson))
        ->assertForbidden();
});

it('renders the admin i18n preview with rtl samples', function () {
    $admin = actingPeopleAdmin(['courses.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.i18n.preview'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/I18nPreview')
            ->has('samples', 3)
            ->where('samples.1.dir', 'rtl')
            ->where('samples.2.dir', 'rtl')
        );
});
