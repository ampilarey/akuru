<?php

use App\Domains\Courses\Actions\AttachAssessmentQuestionAction;
use App\Domains\Courses\Actions\EnrollSelfLearningAction;
use App\Domains\Courses\Actions\ListUnansweredRequiredQuestionsAction;
use App\Domains\Courses\Actions\ReorderAssessmentQuestionsAction;
use App\Domains\Courses\Actions\SaveAssessmentAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Actions\SaveQuestionAction;
use App\Domains\Courses\Actions\TransitionCourseWorkflowAction;
use App\Domains\Courses\Enums\CourseWorkflowStatus;
use App\Domains\Courses\Models\AssessmentQuestion;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Identity\Models\User;
use App\Domains\Progress\Actions\StartAssessmentAttemptAction;
use App\Domains\Progress\Actions\SubmitAssessmentAttemptAction;
use App\Domains\Progress\Models\AssessmentAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §21 "Assessment-Question Pivot".
 *
 * The table is complete — assessment id, question id, position, points
 * override, is required, timestamps — and §21's hardest requirement, the
 * attempt snapshot, genuinely works: `QuestionBankTest` already pins that
 * editing a bank question leaves a stored snapshot untouched.
 *
 * Two of the five fields were inert.
 *
 * **`is_required` was read by nothing.** It is stored, defaulted to true, and
 * copied into every snapshot by `BuildAssessmentSnapshotsAction`:
 *
 *     $snapshot['is_required'] = (bool) $row->is_required;
 *
 * and then no reader anywhere — not the submit path, not the scorer, not the
 * player. A search finds readers for `ContentBlock`'s `is_required` and for
 * the lesson-glossary pivot's, and none at all for this one. So "required"
 * meant nothing: a student could submit with every required question blank, be
 * scored zero on them, and learn of it only from the mark. The attach form did
 * not send the field either, so it was unsettable as well as unenforced.
 *
 * **`position` could never change.** `BuildAssessmentSnapshotsAction` orders
 * every attempt by it, and the only writer was `max(position) + 1` at attach
 * time. There was no reorder route and no control, so the order questions
 * happened to be attached in was the order every student sat them in,
 * permanently — an author who attached the final question before the warm-up
 * had to detach everything and re-attach it in sequence.
 */
uses(RefreshDatabase::class);

function pivotCourse(): array
{
    $admin = actingPeopleAdmin(['courses.manage', 'courses.publish']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Pivot '.uniqueFixtureSuffix(),
        'subject_id' => CourseSubject::query()->value('id'),
        'created_by' => $admin->id,
    ]);
    app(TransitionCourseWorkflowAction::class)->execute($course, CourseWorkflowStatus::InReview, true);
    app(TransitionCourseWorkflowAction::class)->execute($course->fresh(), CourseWorkflowStatus::Published, true);

    $assessment = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id, 'title' => 'Paper', 'status' => 'published', 'max_score' => 10,
    ]);

    return ['admin' => $admin, 'course' => $course->fresh(), 'assessment' => $assessment];
}

function pivotQuestion(string $text, string $type = 'short_answer'): object
{
    return app(SaveQuestionAction::class)->execute([
        'question_type' => $type,
        'question_text' => $text,
        'options' => $type === 'mcq_single' ? [['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']] : null,
        'correct_answer' => $type === 'mcq_single' ? ['a'] : null,
        'acceptable_answers' => $type === 'short_answer' ? ['kitab'] : null,
    ]);
}

function pivotStudent(int $courseId): array
{
    $user = User::factory()->create();
    $student = makeStudent(['user_id' => $user->id, 'first_name' => 'Pivot', 'last_name' => 'Pupil']);
    $enrollment = app(EnrollSelfLearningAction::class)->execute($user->id, $courseId, null);

    return compact('user', 'student', 'enrollment');
}

it('names the required questions a student left blank', function () {
    $action = app(ListUnansweredRequiredQuestionsAction::class);

    $snapshots = [
        ['question_id' => 1, 'is_required' => true, 'title' => 'Required one', 'pattern' => 'text_input'],
        ['question_id' => 2, 'is_required' => false, 'title' => 'Optional', 'pattern' => 'text_input'],
        ['question_id' => 3, 'is_required' => true, 'question_text' => 'Pick one', 'pattern' => 'selection'],
    ];

    expect($action->execute($snapshots, []))->toBe(['Required one', 'Pick one']);

    // Whitespace is not an answer, and neither is an empty selection.
    expect($action->execute($snapshots, ['1' => ['text' => '   '], '3' => ['selected_ids' => []]]))
        ->toBe(['Required one', 'Pick one']);

    expect($action->execute($snapshots, ['1' => ['text' => 'kitab'], '3' => ['selected_ids' => ['a']]]))
        ->toBe([]);
});

it('treats an empty pairing as unanswered, since the player seeds one', function () {
    // `blankAnswers()` seeds `{pairs: {}}` for a mapping question, so an
    // untouched matching question arrives looking like an answer.
    $action = app(ListUnansweredRequiredQuestionsAction::class);
    $snapshots = [['question_id' => 1, 'is_required' => true, 'title' => 'Match', 'pattern' => 'arrange']];

    expect($action->execute($snapshots, ['1' => ['pairs' => []]]))->toBe(['Match'])
        ->and($action->execute($snapshots, ['1' => ['pairs' => ['a' => '']]]))->toBe(['Match'])
        ->and($action->execute($snapshots, ['1' => ['pairs' => ['a' => 'Alif']]]))->toBe([]);
});

it('refuses a submit that leaves a required question blank', function () {
    ['course' => $course, 'assessment' => $assessment] = pivotCourse();
    ['student' => $student, 'enrollment' => $enrollment] = pivotStudent($course->id);

    $required = pivotQuestion('Type the word');
    app(AttachAssessmentQuestionAction::class)->execute([
        'assessment_id' => $assessment->id, 'question_id' => $required->id, 'is_required' => true,
    ]);

    app(StartAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, $student->id, $course->id);

    expect(fn () => app(SubmitAssessmentAttemptAction::class)
        ->execute($assessment->id, $enrollment->id, [], $student->id))
        ->toThrow(ValidationException::class);

    // With the answer supplied it goes through.
    $result = app(SubmitAssessmentAttemptAction::class)->execute(
        $assessment->id,
        $enrollment->id,
        [(string) $required->id => ['text' => 'kitab']],
        $student->id,
    );

    expect($result['attempt']['status'])->not->toBe('in_progress');
});

it('lets an optional question stay blank', function () {
    ['course' => $course, 'assessment' => $assessment] = pivotCourse();
    ['student' => $student, 'enrollment' => $enrollment] = pivotStudent($course->id);

    $optional = pivotQuestion('Anything to add?');
    app(AttachAssessmentQuestionAction::class)->execute([
        'assessment_id' => $assessment->id, 'question_id' => $optional->id, 'is_required' => false,
    ]);

    app(StartAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, $student->id, $course->id);
    $result = app(SubmitAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, [], $student->id);

    expect($result['attempt']['status'])->not->toBe('in_progress');
});

it('does not hold an expired attempt hostage to a blank required question', function () {
    // §31's rule is that time running out scores what was in hand rather than
    // throwing it away. Refusing a late submission for a blank question would
    // strand the student on a page they can no longer act on.
    ['course' => $course] = pivotCourse();
    $assessment = app(SaveAssessmentAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Timed paper',
        'status' => 'published',
        'max_score' => 10,
        'time_limit_minutes' => 1,
    ]);
    ['student' => $student, 'enrollment' => $enrollment] = pivotStudent($course->id);

    $required = pivotQuestion('Type the word');
    app(AttachAssessmentQuestionAction::class)->execute([
        'assessment_id' => $assessment->id, 'question_id' => $required->id, 'is_required' => true,
    ]);

    $attempt = app(StartAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, $student->id, $course->id);
    AssessmentAttempt::query()
        ->whereKey($attempt['id'])
        ->update(['started_at' => now()->subMinutes(30)]);

    $result = app(SubmitAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, [], $student->id);

    expect($result['expired'])->toBeTrue()
        ->and($result['attempt']['status'])->not->toBe('in_progress');
});

it('reorders questions, and refuses a list that is not the whole paper', function () {
    ['course' => $course, 'assessment' => $assessment] = pivotCourse();

    $first = pivotQuestion('One');
    $second = pivotQuestion('Two');
    $third = pivotQuestion('Three');
    foreach ([$first, $second, $third] as $question) {
        app(AttachAssessmentQuestionAction::class)->execute([
            'assessment_id' => $assessment->id, 'question_id' => $question->id,
        ]);
    }

    $order = fn (): array => AssessmentQuestion::query()
        ->where('assessment_id', $assessment->id)
        ->orderBy('position')
        ->pluck('question_id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    expect($order())->toBe([(int) $first->id, (int) $second->id, (int) $third->id]);

    app(ReorderAssessmentQuestionsAction::class)->execute((int) $assessment->id, [
        (int) $third->id, (int) $first->id, (int) $second->id,
    ]);

    expect($order())->toBe([(int) $third->id, (int) $first->id, (int) $second->id]);

    // A partial list would renumber some rows and leave the rest colliding.
    expect(fn () => app(ReorderAssessmentQuestionsAction::class)
        ->execute((int) $assessment->id, [(int) $third->id]))
        ->toThrow(ValidationException::class);
});

it('leaves an attempt already under way on the order it was given', function () {
    // §21's snapshot rule is what makes reordering safe. A student mid-paper
    // keeps the sequence they started with.
    ['course' => $course, 'assessment' => $assessment] = pivotCourse();
    ['student' => $student, 'enrollment' => $enrollment] = pivotStudent($course->id);

    $first = pivotQuestion('One');
    $second = pivotQuestion('Two');
    foreach ([$first, $second] as $question) {
        app(AttachAssessmentQuestionAction::class)->execute([
            'assessment_id' => $assessment->id, 'question_id' => $question->id, 'is_required' => false,
        ]);
    }

    $attempt = app(StartAssessmentAttemptAction::class)->execute($assessment->id, $enrollment->id, $student->id, $course->id);
    $before = array_map(fn (array $s): int => (int) $s['question_id'], $attempt['snapshots']);

    app(ReorderAssessmentQuestionsAction::class)->execute((int) $assessment->id, [
        (int) $second->id, (int) $first->id,
    ]);

    $after = AssessmentAttempt::query()->find($attempt['id'])->snapshots;

    expect(array_map(fn (array $s): int => (int) $s['question_id'], $after))->toBe($before);
});

it('sends the required flag from the attach form and marks it for the student', function () {
    // The controller has always read `is_required` with a default of true, and
    // no control sent it. Both screens now say which questions matter — the
    // refusal is only fair if the page shows what it is refusing over.
    $builder = (string) file_get_contents(base_path('resources/js/Pages/Courses/Catalog/Assessments.jsx'));
    $player = (string) file_get_contents(base_path('resources/js/Pages/Courses/Learn/Assessment.jsx'));

    expect($builder)->toContain("attachForm.setData('is_required'")
        ->and($builder)->toContain('questions/reorder')
        ->and($player)->toContain('snapshot.is_required')
        // A refusal the page does not render is the invisible refusal the §13
        // lesson player had.
        ->and($player)->toContain('errors?.answers');
});
