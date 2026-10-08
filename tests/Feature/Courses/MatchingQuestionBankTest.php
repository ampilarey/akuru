<?php

use App\Domains\Courses\Actions\AttachAssessmentQuestionAction;
use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveQuestionAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\Question;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A matching question saved from the bank kept its answers and lost its
 * pairs (slice MQ1, STATUS §5ow).
 *
 * SPEC §17 Pattern 3 has a mapping mode — "match pairs", "sort items into
 * categories" — whose key is each left item's id paired with its match. The
 * bank's form suggests exactly that key, `{"1": "Alif", "2": "Baa"}`, and
 * `SaveQuestionAction` ran it through `jsonList`, which keeps the values and
 * drops the keys. The question came back as `["Alif", "Baa"]`, the snapshot
 * found no right-hand column, and a learner was shown an ordering with Up and
 * Down buttons. `MatchingQuestionScoringTest` could not see it: it writes its
 * questions with `Question::create`, past the action.
 *
 * Fixing the save made the next fault reachable. With answers shown, the
 * learner's page printed the key with `.join(', ')` — fine for a list, a throw
 * for an object, so a marked "match pairs" attempt was a blank page.
 *
 * This walks the bank's own route and the learner's own player.
 */
uses(RefreshDatabase::class);

function matchingBankCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Matching '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    return [$admin, $course->fresh()];
}

it('keeps the pairs of a matching question the bank saves', function () {
    [$admin] = matchingBankCourse();

    // As the bank's form posts it: both JSON boxes are strings.
    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/questions', [
            'question_type' => 'matching',
            'question_text' => 'Match each letter to its name',
            'options' => json_encode([['id' => '1', 'label' => 'ا'], ['id' => '2', 'label' => 'ب']]),
            'correct_answer' => json_encode(['1' => 'Alif', '2' => 'Baa']),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $question = Question::query()->latest('id')->firstOrFail();
    expect($question->correct_answer)->toBe([1 => 'Alif', 2 => 'Baa']);
});

it('refuses a pairing numbered from 0, which would read back as a list', function () {
    [$admin] = matchingBankCourse();

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/questions', [
            'question_type' => 'matching',
            'question_text' => 'Numbered from nought',
            'options' => json_encode([['id' => '0', 'label' => 'ا'], ['id' => '1', 'label' => 'ب']]),
            'correct_answer' => json_encode((object) ['0' => 'Alif', '1' => 'Baa']),
        ])
        ->assertSessionHasErrors('correct_answer');

    expect(Question::query()->count())->toBe(0);
});

it('still takes a list for every other question, and for an imported matching one', function () {
    // The legacy quiz import hands its matching keys over as lists; refusing
    // them would stop the import. Every other type keeps its list as before.
    $ordering = app(SaveQuestionAction::class)->execute([
        'question_type' => 'arrange',
        'question_text' => 'Put these in order',
        'options' => [['id' => 'a', 'label' => 'First'], ['id' => 'b', 'label' => 'Second']],
        'correct_answer' => json_encode(['a', 'b']),
    ]);
    $imported = app(SaveQuestionAction::class)->execute([
        'question_type' => 'matching',
        'question_text' => 'Imported',
        'correct_answer' => ['Alif', 'Baa'],
    ]);

    expect($ordering->correct_answer)->toBe(['a', 'b'])
        ->and($imported->correct_answer)->toBe(['Alif', 'Baa']);
});

it('lets a learner pair a bank question, marks the pairing, and shows the pairs back', function () {
    [$admin, $course] = matchingBankCourse();
    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->post('/catalog/questions', [
            'question_type' => 'matching',
            'question_text' => 'Match each letter to its name',
            'options' => json_encode([['id' => '1', 'label' => 'ا'], ['id' => '2', 'label' => 'ب']]),
            'correct_answer' => json_encode(['1' => 'Alif', '2' => 'Baa']),
        ])
        ->assertRedirect();
    $question = Question::query()->latest('id')->firstOrFail();

    $assessment = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Letters',
        'status' => 'published',
        'show_correct_answers' => true,
        'created_by' => $admin->id,
    ]);
    app(AttachAssessmentQuestionAction::class)->execute([
        'assessment_id' => $assessment->id,
        'question_id' => $question->id,
        'points_override' => 2,
    ]);

    $user = User::factory()->create();
    makeStudent(['user_id' => $user->id, 'first_name' => 'Pairs']);
    app(EnrollSelfLearningAction::class)->execute($user->id, $course->id);

    // The right-hand column reaches the learner; the key does not.
    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.assessments.show', $assessment->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('attempt.snapshots.0.targets', [['id' => 'Alif', 'label' => 'Alif'], ['id' => 'Baa', 'label' => 'Baa']])
            ->missing('attempt.snapshots.0.correct_answer'));

    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->post(route('learn.assessments.submit', $assessment->id), [
            'answers' => [(string) $question->id => ['pairs' => ['1' => 'Alif', '2' => 'Baa']]],
        ])
        ->assertRedirect();

    expect(AssessmentAttempt::query()->firstOrFail()->score)->toBe(2);

    // Marked, with answers shown: the key comes back as the pairing it is.
    $this->actingAs($user)
        ->withoutLocalizationMiddleware()
        ->get(route('learn.assessments.show', $assessment->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('attempt.snapshots.0.correct_answer', ['1' => 'Alif', '2' => 'Baa']));
});

it('draws a pairing as pairs on the learner’s page instead of joining it as a list', function () {
    $source = (string) file_get_contents(resource_path('js/Pages/Courses/Learn/Assessment.jsx'));

    // `.join` on an object throws, and the page with it.
    expect($source)->not->toContain('(snapshot.correct_answer || []).join')
        ->and($source)->toContain('correctAnswer(snapshot)')
        ->and($source)->toContain('Object.entries(key)');
});
