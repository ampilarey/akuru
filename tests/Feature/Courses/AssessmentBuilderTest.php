<?php

use App\Domains\Courses\Actions\AttachAssessmentQuestionAction;
use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\PublishLessonAction;
use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\Courses\Actions\SaveContentBlockAction;
use App\Domains\Courses\Actions\SaveCourseModuleAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveLessonAction;
use App\Domains\Courses\Actions\SaveQuestionAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function publishAssessmentCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Assessment Lab',
        'subject_id' => CourseSubject::query()->where('slug', 'nahw')->value('id'),
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

    $question = app(SaveQuestionAction::class)->execute([
        'question_type' => 'mcq_single',
        'question_text' => 'What is kitab?',
        'options' => [['id' => 'a', 'label' => 'Book'], ['id' => 'b', 'label' => 'Pen']],
        'correct_answer' => ['a'],
    ]);

    $assessment = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Lesson quiz',
        'assessment_type' => 'lesson_quiz',
        'status' => 'published',
        'retake_limit' => 1,
        'show_correct_answers' => true,
        'created_by' => $admin->id,
    ]);
    app(AttachAssessmentQuestionAction::class)->execute([
        'assessment_id' => $assessment->id,
        'question_id' => $question->id,
        'points_override' => 5,
    ]);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Yusuf']);
    app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    return compact('admin', 'course', 'question', 'assessment', 'user');
}

it('builds a course assessment from the question bank and exports csv', function () {
    ['admin' => $admin, 'course' => $course, 'assessment' => $assessment] = publishAssessmentCourse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.courses.assessments.index', $course->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/Assessments')
            ->has('assessments', 1)
            ->where('assessments.0.max_score', 5)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.courses.assessments.export', $course->id))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($assessment->fresh()->max_score)->toBe(5);
});

it('snapshots questions so later bank edits do not change the attempt', function () {
    ['question' => $question, 'assessment' => $assessment, 'user' => $user] = publishAssessmentCourse();

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.assessments.show', $assessment->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Learn/Assessment')
            ->missing('attempt.snapshots.0.correct_answer')
        );

    $attempt = AssessmentAttempt::query()->first();
    expect($attempt?->snapshots[0]['correct_answer'])->toBe(['a']);

    app(SaveQuestionAction::class)->execute([
        'question_type' => 'mcq_single',
        'question_text' => 'Changed later',
        'options' => [['id' => 'a', 'label' => 'Book'], ['id' => 'b', 'label' => 'Pen']],
        'correct_answer' => ['b'],
    ], $question->fresh());

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.assessments.autosave', $assessment->id), [
            'answers' => [(string) $question->id => ['selected_ids' => ['a']]],
        ])
        ->assertRedirect();

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.assessments.submit', $assessment->id), [
            'answers' => [(string) $question->id => ['selected_ids' => ['a']]],
        ])
        ->assertRedirect();

    $scored = AssessmentAttempt::query()->first();
    expect($scored?->status->value)->toBe('scored')
        ->and($scored?->score)->toBe(5)
        ->and($scored?->snapshots[0]['correct_answer'])->toBe(['a'])
        ->and($question->fresh()->correct_answer)->toBe(['b']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->get(route('learn.assessments.show', $assessment->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempt.snapshots.0.correct_answer', ['a'])
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($user)
        ->post(route('learn.assessments.submit', $assessment->id), [
            'answers' => [(string) $question->id => ['selected_ids' => ['a']]],
        ])
        ->assertSessionHasErrors('attempt');
});
