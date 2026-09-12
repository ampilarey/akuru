<?php

use App\Domains\Courses\Actions\ScoreActivityAnswersAction;
use App\Domains\Courses\Actions\ScoreAssessmentSnapshotsAction;
use App\Domains\Courses\Actions\SnapshotQuestionAction;
use App\Domains\Courses\Enums\QuestionType;
use App\Domains\Courses\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * SPEC §17: "All activity types must use 4 base patterns. New activity types
 * should be added through **configuration** of these patterns, not through new
 * hardcoded code paths."
 *
 * Pattern 3, "Drag / Arrange Interaction", lists six examples:
 *
 *   > Match pairs · Arrange words · Arrange steps · Sentence builder ·
 *   > Ordering process · Sort items into categories
 *
 * Four are **orderings**; two are **mappings**. Only the ordering half existed
 * — `scoreArrange()` compared `correct_order` against the student's list — so
 * "match pairs" and "sort into categories" could not be built. `Matching` was
 * routed to Pattern 1 instead, where `scoreSelection` compares an **unordered
 * set of ids**: a student who picked every right-hand item and paired them all
 * wrongly scored full marks.
 *
 * Mapping is now a configuration of Pattern 3, which is what §17 asks for. A
 * fifth pattern would have been the thing §17 forbids.
 */
uses(RefreshDatabase::class);

function matchingActivity(): array
{
    return [
        'pattern' => 'arrange',
        'max_score' => 6,
        'data' => ['correct_pairs' => ['1' => 'Alif', '2' => 'Baa', '3' => 'Taa']],
    ];
}

it('routes matching to Pattern 3, not Pattern 1', function () {
    // §17 lists "Match pairs" under Drag/Arrange. Selection cannot express a
    // pairing at all, which is why the old mapping mis-scored silently.
    expect(QuestionType::Matching->pattern()->value)->toBe('arrange');
});

it('scores a correct pairing', function () {
    $result = app(ScoreActivityAnswersAction::class)->execute(
        matchingActivity(),
        ['pairs' => ['1' => 'Alif', '2' => 'Baa', '3' => 'Taa']],
    );

    expect($result['score'])->toBe(6)
        ->and($result['passed'])->toBeTrue();
});

it('gives nothing when the right items are chosen but paired wrongly', function () {
    // Precisely the case Pattern 1 scored as full marks: every correct value is
    // present, and not one is against the right item.
    $result = app(ScoreActivityAnswersAction::class)->execute(
        matchingActivity(),
        ['pairs' => ['1' => 'Baa', '2' => 'Taa', '3' => 'Alif']],
    );

    expect($result['score'])->toBe(0);
});

it('gives nothing for an incomplete pairing', function () {
    $result = app(ScoreActivityAnswersAction::class)->execute(
        matchingActivity(),
        ['pairs' => ['1' => 'Alif', '2' => 'Baa']],
    );

    expect($result['score'])->toBe(0);
});

it('ignores an item the student left blank rather than counting it as an answer', function () {
    $result = app(ScoreActivityAnswersAction::class)->execute(
        matchingActivity(),
        ['pairs' => ['1' => 'Alif', '2' => 'Baa', '3' => 'Taa', '4' => '']],
    );

    // A left-hand item with nothing chosen is not an answer. Keeping the empty
    // entry would fail a pairing that is in fact complete.
    expect($result['score'])->toBe(6);
});

it('sorts items into categories, which is the same shape', function () {
    // §17 lists "Sort items into categories" under Pattern 3 too. Many lefts
    // share one right, which a pairing map expresses without any new code.
    $result = app(ScoreActivityAnswersAction::class)->execute([
        'pattern' => 'arrange',
        'max_score' => 4,
        'data' => ['correct_pairs' => ['alif' => 'letter', 'baa' => 'letter', 'fatha' => 'haraka']],
    ], ['pairs' => ['alif' => 'letter', 'baa' => 'letter', 'fatha' => 'haraka']]);

    expect($result['score'])->toBe(4);
});

it('still scores an ordering question by order', function () {
    // The other four Pattern 3 examples must be untouched.
    $result = app(ScoreActivityAnswersAction::class)->execute([
        'pattern' => 'arrange',
        'max_score' => 3,
        'data' => ['correct_order' => ['a', 'b', 'c']],
    ], ['order' => ['a', 'b', 'c']]);

    expect($result['score'])->toBe(3);
});

it('sends the student the right-hand column but never the answer key', function () {
    $question = Question::query()->create([
        'question_type' => 'matching',
        'pattern' => 'arrange',
        'question_text' => 'Match each letter to its name',
        'options' => [['id' => '1', 'label' => 'ا'], ['id' => '2', 'label' => 'ب']],
        'correct_answer' => ['1' => 'Alif', '2' => 'Baa'],
    ]);

    $snapshot = app(SnapshotQuestionAction::class)->execute($question);

    // The right-hand column is the question, not the answer — a student cannot
    // pair without seeing it. It is derived on the server because
    // `correct_answer` is stripped from the student's snapshot, and sorted so
    // the order it was written in does not hint at the pairing.
    expect($snapshot['targets'])->toBe([
        ['id' => 'Alif', 'label' => 'Alif'],
        ['id' => 'Baa', 'label' => 'Baa'],
    ]);
});

it('leaves an ordering question without targets', function () {
    $question = Question::query()->create([
        'question_type' => 'arrange',
        'pattern' => 'arrange',
        'question_text' => 'Put these in order',
        'options' => [['id' => 'a'], ['id' => 'b']],
        'correct_answer' => ['b', 'a'],
    ]);

    // A list answer key is an ordering. The shape is what tells them apart, so
    // no existing question changes behaviour.
    expect(app(SnapshotQuestionAction::class)->execute($question)['targets'])->toBeNull();
});

it('scores a matching question through the assessment path', function () {
    $snapshots = [[
        'question_id' => 1,
        'points' => 5,
        'pattern' => 'arrange',
        'options' => [['id' => '1'], ['id' => '2']],
        'correct_answer' => ['1' => 'Alif', '2' => 'Baa'],
    ]];

    $result = app(ScoreAssessmentSnapshotsAction::class)->execute(
        $snapshots,
        ['1' => ['pairs' => ['1' => 'Alif', '2' => 'Baa']]],
    );

    expect($result['score'])->toBe(5)
        ->and($result['status'])->toBe('scored');
});
