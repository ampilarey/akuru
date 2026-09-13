<?php

use App\Domains\Courses\Actions\RecordCourseReviewDecisionAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseReviewDecision as Decision;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * SPEC §35 "Dean / Supervisor Dashboard" names eight abilities. Three had
 * nowhere to be recorded:
 *
 *   > Approve courses · **Reject courses** · **Request changes**
 *
 * and SPEC §34 "Course Creator Dashboard" names the other half of the same
 * missing thing:
 *
 *   > **View supervisor comments**
 *
 * The workflow itself works — `draft → in_review → published → archived`, with
 * `in_review → draft` as the way back, properly enforced. The catalog screen
 * has the buttons: "Submit review", "Publish", "Return draft", "Archive".
 *
 * **"Return draft" carried no reason at all.** No comment, no reviewer, no
 * date, and no distinction between a rejection and a request for changes. A
 * creator whose course was bounced back was told nothing, and the supervisor's
 * review — the only part of the exchange with any content in it — was
 * discarded the instant the button was pressed.
 *
 * So four §34/§35 abilities rested on a record nobody kept. Not a broken
 * calculation, and not a missing screen: a **missing record**.
 */
uses(RefreshDatabase::class);

function reviewAdmin(array $permissions = ['courses.manage', 'courses.publish']): User
{
    return actingPeopleAdmin($permissions);
}

function courseInReview(User $admin): object
{
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Review me '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);

    return $course->fresh();
}

it('records why a course was sent back, which nothing did before', function () {
    $admin = reviewAdmin();
    $course = courseInReview($admin);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/courses/'.$course->id.'/review-decision', [
            'decision' => 'changes_requested',
            'comment' => 'Lesson 3 has no assessment.',
        ])
        ->assertRedirect();

    $decisions = app(RecordCourseReviewDecisionAction::class)->forCourse($course->id);

    expect($decisions)->toHaveCount(1);
    expect($decisions[0]['decision'])->toBe('changes_requested');
    expect($decisions[0]['comment'])->toBe('Lesson 3 has no assessment.');
    expect($decisions[0]['from_status'])->toBe('in_review');
    expect($decisions[0]['to_status'])->toBe('draft');
    expect($decisions[0]['reviewer_id'])->toBe($admin->id);
    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::Draft);
});

it('refuses a refusal with no reason', function () {
    // The defect this slice replaces is a course bounced back with nothing
    // said. Re-creating it behind a nullable column would be a poor joke.
    $admin = reviewAdmin();
    $course = courseInReview($admin);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/courses/'.$course->id.'/review-decision', [
            'decision' => 'rejected',
            'comment' => '   ',
        ])
        ->assertSessionHasErrors('comment');

    // Nothing moved and nothing was written.
    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::InReview);
    expect(app(RecordCourseReviewDecisionAction::class)->forCourse($course->id))->toHaveCount(0);
});

it('lets an approval be silent, because it says nothing is wrong', function () {
    $admin = reviewAdmin();
    $course = courseInReview($admin);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/courses/'.$course->id.'/review-decision', ['decision' => 'approved'])
        ->assertRedirect();

    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::Published);
    expect(app(RecordCourseReviewDecisionAction::class)->forCourse($course->id)[0]['comment'])->toBeNull();
});

it('keeps publishing behind courses.publish', function () {
    // The transition is still the authority. A reviewer who cannot publish
    // cannot approve, and no decision is recorded for a move that was refused.
    $admin = reviewAdmin(['courses.manage']);
    $course = courseInReview(reviewAdmin());

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/courses/'.$course->id.'/review-decision', ['decision' => 'approved'])
        ->assertSessionHasErrors('workflow_status');

    expect($course->fresh()->workflow_status)->toBe(CourseWorkflowStatus::InReview);
    expect(app(RecordCourseReviewDecisionAction::class)->forCourse($course->id))->toHaveCount(0);
});

it('refuses a decision on a course nobody submitted for review', function () {
    // §35's abilities are about a course "submitted for review". A comment on a
    // draft would appear on the creator's screen about a step that never
    // happened.
    $admin = reviewAdmin();
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Still a draft '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/courses/'.$course->id.'/review-decision', [
            'decision' => 'changes_requested',
            'comment' => 'Not ready.',
        ])
        ->assertSessionHasErrors('decision');
});

it('shows the creator every round, newest first', function () {
    // §34 "View supervisor comments" — plural. A creator who has been through
    // two rounds needs both, not only the latest verdict.
    $admin = reviewAdmin();
    $course = courseInReview($admin);

    $this->actingAs($admin)->withoutLocalizationMiddleware()
        ->post('/catalog/courses/'.$course->id.'/review-decision', [
            'decision' => 'changes_requested', 'comment' => 'First round.',
        ]);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::InReview, true);
    $this->actingAs($admin)->withoutLocalizationMiddleware()
        ->post('/catalog/courses/'.$course->id.'/review-decision', [
            'decision' => 'rejected', 'comment' => 'Second round.',
        ]);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/courses')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/Index')
            ->has('rows.0.review_decisions', 2)
            // Newest first.
            ->where('rows.0.review_decisions.0.comment', 'Second round.')
            ->where('rows.0.review_decisions.1.comment', 'First round.')
            ->has('decisions', 3));
});

it('never edits a decision that has already been made', function () {
    // Append-only: a second decision is a new row. The creator sees what was
    // asked of them and when, not a single verdict that keeps changing.
    $admin = reviewAdmin();
    $course = courseInReview($admin);

    $first = app(RecordCourseReviewDecisionAction::class)->execute(
        $course, Decision::ChangesRequested, ['comment' => 'Fix the outline.'], $admin->id, true,
    );
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::InReview, true);
    $second = app(RecordCourseReviewDecisionAction::class)->execute(
        $course->fresh(), Decision::Approved, [], $admin->id, true,
    );

    expect($second['id'])->not->toBe($first['id']);
    $all = app(RecordCourseReviewDecisionAction::class)->forCourse($course->id);
    expect($all)->toHaveCount(2);
    expect($all->pluck('comment')->all())->toContain('Fix the outline.');
});
