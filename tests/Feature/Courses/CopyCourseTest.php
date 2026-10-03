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
use App\Domains\Courses\Enums\AssessmentStatus;
use App\Domains\Courses\Enums\CertificateKind;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Enums\LessonStatus;
use App\Domains\Courses\Enums\ModuleStatus;
use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\Assessment;
use App\Domains\Courses\Models\AssessmentQuestion;
use App\Domains\Courses\Models\CertificateTemplate;
use App\Domains\Courses\Models\ContentBlock;
use App\Domains\Courses\Models\Course;
use App\Domains\Courses\Models\CourseEnrollment;
use App\Domains\Courses\Models\CourseModule;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\Lesson;
use App\Domains\Courses\Models\Question;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Moodle parity slice M1 (STATUS §5oh). The owner, 2026-10-03, after asking
 * whether Akuru has Moodle's course-building tools: "Yes build". A whole course
 * is copied as a new draft — its design, never what happened in it.
 */
uses(RefreshDatabase::class);

function copySourceCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Nahw one',
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    $module = app(SaveCourseModuleAction::class)->execute(['course_id' => $course->id, 'title' => 'Unit 1', 'created_by' => $admin->id]);
    $first = app(SaveLessonAction::class)->execute(['course_module_id' => $module->id, 'title' => 'Nouns', 'created_by' => $admin->id]);
    $second = app(SaveLessonAction::class)->execute(['course_module_id' => $module->id, 'title' => 'Verbs', 'created_by' => $admin->id]);

    $own = app(SaveQuestionAction::class)->execute(['question_type' => 'essay', 'question_text' => 'Explain the noun.', 'course_id' => $course->id]);
    $bank = app(SaveQuestionAction::class)->execute(['question_type' => 'essay', 'question_text' => 'A shared bank question.']);
    $quiz = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id, 'course_module_id' => $module->id, 'lesson_id' => $first->id,
        'title' => 'Nouns quiz', 'assessment_type' => 'lesson_quiz', 'status' => 'published', 'passing_score' => 5, 'max_score' => 10, 'created_by' => $admin->id,
    ]);
    app(AttachAssessmentQuestionAction::class)->execute(['assessment_id' => $quiz->id, 'question_id' => $own->id, 'points_override' => 6]);
    app(AttachAssessmentQuestionAction::class)->execute(['assessment_id' => $quiz->id, 'question_id' => $bank->id, 'points_override' => 4]);

    app(SaveContentBlockAction::class)->execute(['lesson_id' => $first->id, 'type' => 'text', 'data' => ['body' => 'A noun names a thing.'], 'created_by' => $admin->id]);
    app(SaveContentBlockAction::class)->execute(['lesson_id' => $first->id, 'type' => 'quiz_embed', 'data' => ['quiz_id' => $quiz->id, 'title' => 'Try it'], 'created_by' => $admin->id]);
    $activity = Activity::query()->create([
        'course_id' => $course->id, 'course_module_id' => $module->id, 'lesson_id' => $second->id,
        'title' => 'Write three verbs', 'pattern' => 'teacher_marked', 'activity_type' => 'written_response',
        'data' => ['prompt' => 'Write three verbs.'], 'max_score' => 5, 'is_required' => true, 'created_by' => $admin->id,
    ]);
    $second->update(['unlock_rule' => ['mode' => 'pass_assessment', 'assessment_id' => $quiz->id]]);
    CertificateTemplate::query()->create([
        'name' => 'Nahw one certificate', 'kind' => CertificateKind::CourseCompletion->value, 'course_id' => $course->id,
        'rules' => ['assessment_id' => $quiz->id, 'require_final_assessment' => true], 'active' => true, 'created_by' => $admin->id,
    ]);

    foreach ([$first, $second] as $lesson) {
        app(PublishLessonAction::class)->execute($lesson->fresh(), $admin->id);
    }
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    // Something happened in the original: a learner is enrolled.
    $learner = User::factory()->create();
    makeStudent(['user_id' => $learner->id]);
    app(EnrollSelfLearningAction::class)->execute($learner->id, $course->id);

    return compact('admin', 'course', 'module', 'first', 'second', 'own', 'bank', 'quiz', 'activity');
}

it('copies the whole course design as a new draft and opens its outline', function () {
    ['admin' => $admin, 'course' => $course, 'quiz' => $quiz, 'own' => $own, 'bank' => $bank, 'activity' => $activity] = copySourceCourse();

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.courses.copy', $course->id), ['title' => 'Nahw one — 2027 intake'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $copy = Course::query()->where('title', 'Nahw one — 2027 intake')->sole();
    expect($copy->id)->not->toBe($course->id)
        ->and($copy->workflow_status)->toBe(CourseWorkflowStatus::Draft)
        ->and($copy->status)->toBe('closed')
        ->and($copy->slug)->not->toBe($course->slug)
        ->and($copy->subject_id)->toBe($course->subject_id)
        ->and($copy->meta['copied_from_course_id'])->toBe($course->id);

    // Structure: one module, two lessons, all drafts with nothing published.
    $module = CourseModule::query()->where('course_id', $copy->id)->sole();
    $lessons = Lesson::query()->where('course_id', $copy->id)->orderBy('position')->get();
    expect($module->title)->toBe('Unit 1')->and($module->status)->toBe(ModuleStatus::Draft)
        ->and($lessons->pluck('title')->all())->toBe(['Nouns', 'Verbs'])
        ->and($lessons->every(fn (Lesson $lesson): bool => $lesson->status === LessonStatus::Draft))->toBeTrue()
        ->and($lessons->pluck('current_revision_id')->filter()->all())->toBe([])
        ->and($lessons->pluck('course_module_id')->unique()->all())->toBe([$module->id]);

    // The test comes with its questions: the course's own copied, the bank's linked.
    $copiedQuiz = Assessment::query()->where('course_id', $copy->id)->sole();
    $links = AssessmentQuestion::query()->where('assessment_id', $copiedQuiz->id)->orderBy('position')->get();
    $copiedOwn = Question::query()->where('course_id', $copy->id)->sole();
    expect($copiedQuiz->status)->toBe(AssessmentStatus::Draft)
        ->and($copiedQuiz->lesson_id)->toBe($lessons[0]->id)
        ->and($copiedOwn->id)->not->toBe($own->id)
        ->and($copiedOwn->question_text)->toBe('Explain the noun.')
        ->and($links->pluck('question_id')->sort()->values()->all())->toBe(collect([$copiedOwn->id, $bank->id])->sort()->values()->all())
        ->and($links->firstWhere('question_id', $copiedOwn->id)->points_override)->toBe(6);

    // Blocks, activity, unlock rule and certificate point at the copy's own items.
    $blocks = ContentBlock::query()->where('lesson_id', $lessons[0]->id)->orderBy('position')->get();
    expect($blocks->pluck('type')->all())->toBe(['text', 'quiz_embed'])
        ->and($blocks[0]->data['body'])->toBe('A noun names a thing.')
        ->and($blocks[1]->data['quiz_id'])->toBe($copiedQuiz->id)
        ->and($blocks->pluck('course_id')->unique()->all())->toBe([$copy->id]);
    $copiedActivity = Activity::query()->where('course_id', $copy->id)->sole();
    expect($copiedActivity->id)->not->toBe($activity->id)
        ->and($copiedActivity->lesson_id)->toBe($lessons[1]->id)
        ->and((bool) $copiedActivity->is_required)->toBeTrue()
        ->and($lessons[1]->unlock_rule['assessment_id'])->toBe($copiedQuiz->id);
    $certificate = CertificateTemplate::query()->where('course_id', $copy->id)->sole();
    expect($certificate->rules['assessment_id'])->toBe($copiedQuiz->id);

    // Nothing that happened in the original comes along.
    expect(CourseEnrollment::query()->where('course_id', $copy->id)->count())->toBe(0);

    // The original is untouched.
    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::Published)
        ->and(Assessment::query()->find($quiz->id)->status)->toBe(AssessmentStatus::Published)
        ->and(ContentBlock::query()->where('course_id', $course->id)->count())->toBe(2)
        ->and(Lesson::query()->where('course_id', $course->id)->whereNotNull('current_revision_id')->count())->toBe(2);
});

it('names the copy after the original when no title is given, and offers Copy on the catalog', function () {
    ['admin' => $admin, 'course' => $course] = copySourceCourse();

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.courses.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Index')->where('t.copy', 'Copy'));

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.courses.copy', $course->id))
        ->assertSessionHasNoErrors();

    expect(Course::query()->where('title', 'Nahw one (copy)')->exists())->toBeTrue();
    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(trans('teach.copy_hint', [], $locale))->not->toBe('teach.copy_hint');
    }
});

it('refuses a copy to anyone who cannot manage courses', function () {
    ['course' => $course] = copySourceCourse();
    $learner = User::factory()->create();

    $this->withoutLocalizationMiddleware()->actingAs($learner)
        ->post(route('catalog.courses.copy', $course->id))
        ->assertForbidden();

    expect(Course::query()->count())->toBe(1);
});
