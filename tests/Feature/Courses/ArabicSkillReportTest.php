<?php

use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\ArabicLetter;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('reports skill-tagged activity scores for staff and the student', function () {
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Skill report lab',
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);
    $module = app(SaveCourseModuleAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Start',
        'created_by' => $admin->id,
    ]);
    $lesson = app(SaveLessonAction::class)->execute([
        'course_module_id' => $module->id,
        'title' => 'Lesson',
        'created_by' => $admin->id,
    ]);
    app(SaveContentBlockAction::class)->execute([
        'lesson_id' => $lesson->id,
        'type' => 'text',
        'data' => ['body' => 'Body'],
        'created_by' => $admin->id,
    ]);
    app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);

    $baa = ArabicLetter::query()->where('key_name', 'baa')->firstOrFail();
    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Hear baa',
        'pattern' => 'selection',
        'max_score' => 10,
        'data' => [
            'prompt' => 'Choose',
            'options' => [['id' => 'baa', 'label' => 'ب'], ['id' => 'taa', 'label' => 'ت']],
            'correct_ids' => ['baa'],
        ],
        'settings' => [
            'skill' => 'listening',
            'letter_id' => $baa->id,
            'show_correct_answer' => true,
        ],
    ]);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Hassan']);
    app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.activities.submit', $activity->id), [
            'answers' => ['selected_ids' => ['baa']],
        ])
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.arabic.reports'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/ArabicReport')
            ->has('rows', 1)
            ->where('rows.0.skill', 'listening')
            ->where('rows.0.attempts', 1)
            ->where('rows.0.average_score', 10)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.arabic-report'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/ArabicReport')
            ->where('rows.0.average_score', 10)
        );
});
