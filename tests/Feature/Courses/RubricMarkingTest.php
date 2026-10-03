<?php

use App\Domains\Courses\Actions\CopyCourseAction;
use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\Activity;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\Rubric;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Actions\SubmitActivityAttemptAction;
use App\Domains\Progress\Enums\ActivityAttemptStatus;
use App\Domains\Progress\Models\ActivityAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Moodle parity slice M2 (STATUS §5oi): rubrics for teacher-marked work. The
 * owner, 2026-10-03, on Moodle's course-building tools: "Yes build".
 */
uses(RefreshDatabase::class);

function rubricFixture(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Essay writing',
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);
    $activity = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Write a paragraph',
        'pattern' => 'teacher_marked',
        'max_score' => 10,
        'data' => ['prompt' => 'Describe your island.', 'submission_kind' => 'written'],
    ]);

    return ['admin' => $admin, 'course' => $course->fresh(), 'activity' => $activity];
}

/** Two criteria: Content 0/2/4, Language 0/2/4 — best possible 8. */
function rubricPayload(array $extra = []): array
{
    return $extra + [
        'title' => 'Paragraph rubric',
        'description' => 'For short written answers.',
        'criteria' => [
            ['id' => 'content', 'title' => 'Content', 'levels' => [
                ['id' => 'c0', 'label' => 'Not yet', 'points' => 0],
                ['id' => 'c2', 'label' => 'Good', 'points' => 2],
                ['id' => 'c4', 'label' => 'Excellent', 'points' => 4],
            ]],
            ['id' => 'language', 'title' => 'Language', 'levels' => [
                ['id' => 'l0', 'label' => 'Not yet', 'points' => 0],
                ['id' => 'l2', 'label' => 'Good', 'points' => 2],
                ['id' => 'l4', 'label' => 'Excellent', 'points' => 4],
            ]],
        ],
    ];
}

function rubricSubmission(int $courseId, int $activityId): array
{
    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Rubric', 'last_name' => 'Learner']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $courseId, null);
    app(SubmitActivityAttemptAction::class)->execute($activityId, (int) $enrollment->id, (int) $enrollment->unified_student_id, $courseId, ['text' => 'Our island has a lagoon.']);

    return ['user' => $user, 'attempt' => ActivityAttempt::query()->where('activity_id', $activityId)->sole()];
}

it('builds a rubric for a course and attaches it to a teacher-marked activity', function () {
    ['admin' => $admin, 'course' => $course, 'activity' => $activity] = rubricFixture();

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.courses.rubrics.store', $course->id), rubricPayload(['activity_ids' => [$activity->id]]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('catalog.courses.rubrics.index', $course->id));

    $rubric = Rubric::query()->sole();
    expect($rubric->course_id)->toBe($course->id)
        ->and($rubric->maxPoints())->toBe(8)
        ->and(collect($rubric->criteria)->pluck('id')->all())->toBe(['content', 'language'])
        ->and(Activity::query()->find($activity->id)->rubric_id)->toBe($rubric->id);

    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.courses.rubrics.index', $course->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Catalog/Rubrics')
            ->where('rubrics.0.title', 'Paragraph rubric')
            ->where('rubrics.0.max_points', 8)
            ->where('rubrics.0.activity_ids', [$activity->id])
            ->where('activities.0.teacher_marked', true)
            ->where('t.rubric_new', 'New rubric'));

    // A criterion needs a title and two levels; the whole rubric is refused.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.courses.rubrics.store', $course->id), [
            'title' => 'Broken',
            'criteria' => [['title' => 'Only one level', 'levels' => [['label' => 'Done', 'points' => 1]]]],
        ])
        ->assertSessionHasErrors('criteria.0');
    expect(Rubric::query()->count())->toBe(1);
});

it('marks by rubric: a level per criterion, the mark scaled to the item, the levels shown to the learner', function () {
    ['admin' => $admin, 'course' => $course, 'activity' => $activity] = rubricFixture();
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.courses.rubrics.store', $course->id), rubricPayload(['activity_ids' => [$activity->id]]));
    ['user' => $learner, 'attempt' => $attempt] = rubricSubmission($course->id, $activity->id);

    // The marker's queue carries the rubric.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->get(route('catalog.reviews.index'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('rows.0.rubric.title', 'Paragraph rubric')
            ->where('rows.0.rubric.max_points', 8)
            ->where('teach.rubric_mark_total', 'Rubric: :points of :max points, so :score out of :out_of'));

    // Every criterion must have a level.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.reviews.store'), ['kind' => 'activity', 'attempt_id' => $attempt->id, 'rubric' => ['content' => 'c4']])
        ->assertSessionHasErrors('rubric');
    expect($attempt->fresh()->status)->toBe(ActivityAttemptStatus::Submitted);

    // Content excellent (4) + language good (2) = 6 of 8 → 7.5 → 8 out of 10.
    // A typed score is ignored when the item has a rubric.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.reviews.store'), [
            'kind' => 'activity', 'attempt_id' => $attempt->id, 'score' => 1, 'feedback' => 'Lovely lagoon.',
            'rubric' => ['content' => 'c4', 'language' => 'l2'],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('catalog.reviews.index'));

    $marked = $attempt->fresh();
    expect($marked->status)->toBe(ActivityAttemptStatus::Scored)
        ->and($marked->score)->toBe(8)
        ->and($marked->max_score)->toBe(10)
        ->and($marked->feedback)->toBe('Lovely lagoon.')
        ->and($marked->rubric_scores['points'])->toBe(6)
        ->and($marked->rubric_scores['max_points'])->toBe(8)
        ->and(collect($marked->rubric_scores['criteria'])->pluck('level', 'title')->all())->toBe(['Content' => 'Excellent', 'Language' => 'Good']);

    // The learner sees the levels.
    $this->withoutLocalizationMiddleware()->actingAs($learner)
        ->get(route('learn.activities.show', $activity->id))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Courses/Learn/Activity')
            ->where('attempt.rubric_scores.criteria.0.level', 'Excellent')
            ->where('attempt.rubric_scores.score', 8)
            ->where('teach.rubric_result', 'How it was marked'));

    // Editing the rubric later never changes a mark already given.
    $rubric = Rubric::query()->sole();
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->put(route('catalog.courses.rubrics.update', [$course->id, $rubric->id]), rubricPayload(['title' => 'Renamed', 'activity_ids' => [$activity->id]]))
        ->assertSessionHasNoErrors();
    expect($attempt->fresh()->rubric_scores['title'])->toBe('Paragraph rubric');

    // Deleting it puts the item back on a typed score.
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->delete(route('catalog.courses.rubrics.destroy', [$course->id, $rubric->id]))
        ->assertRedirect();
    expect(Activity::query()->find($activity->id)->rubric_id)->toBeNull()
        ->and($attempt->fresh()->rubric_scores['points'])->toBe(6);
});

it('copies the rubrics with the course, the copy marking with its own', function () {
    ['admin' => $admin, 'course' => $course, 'activity' => $activity] = rubricFixture();
    $this->withoutLocalizationMiddleware()->actingAs($admin)
        ->post(route('catalog.courses.rubrics.store', $course->id), rubricPayload(['activity_ids' => [$activity->id]]));

    $copy = app(CopyCourseAction::class)->execute($course, ['title' => 'Essay writing again'], $admin->id);

    $copiedRubric = Rubric::query()->where('course_id', $copy->id)->sole();
    $copiedActivity = Activity::query()->where('course_id', $copy->id)->sole();
    expect($copiedRubric->id)->not->toBe(Rubric::query()->where('course_id', $course->id)->value('id'))
        ->and($copiedRubric->maxPoints())->toBe(8)
        ->and($copiedActivity->rubric_id)->toBe($copiedRubric->id);
});

it('keeps rubrics to people who manage courses', function () {
    ['course' => $course] = rubricFixture();

    $this->withoutLocalizationMiddleware()->actingAs(User::factory()->create())
        ->post(route('catalog.courses.rubrics.store', $course->id), rubricPayload())
        ->assertForbidden();

    foreach (['en', 'dv', 'ar'] as $locale) {
        expect(trans('teach.rubrics_intro', [], $locale))->not->toBe('teach.rubrics_intro');
    }
});
