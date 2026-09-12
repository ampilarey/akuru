<?php

use App\Domains\Courses\Actions\ScoreAssessmentSnapshotsAction;
use App\Domains\Courses\Enums\QuestionType;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * SPEC §20 lists "Arrange/order" among the question types the bank must
 * support, and the machinery for it existed at every layer — `scoreArrange()`,
 * `ActivityPattern::Arrange`, `QuestionType::Arrange->pattern()`, and sample
 * options in the question-bank UI.
 *
 * The **assessment player had no control for it**, while `blankAnswers()`
 * seeded `{order: …}` from the order the options happened to be in. So the
 * student submitted an answer they were never shown and could not change: full
 * marks if the correct order matched the presented one, zero otherwise.
 *
 * These tests pin the scoring the control now feeds. `AnswerControlsExistTest`
 * pins the control itself.
 */
uses(RefreshDatabase::class);

function arrangeSnapshot(): array
{
    return [[
        'question_id' => 1,
        'points' => 6,
        'pattern' => 'arrange',
        'options' => [['id' => 'a'], ['id' => 'b'], ['id' => 'c']],
        'correct_answer' => ['c', 'a', 'b'],
    ]];
}

it('maps the arrange question type to the arrange pattern', function () {
    expect(QuestionType::Arrange->pattern()->value)->toBe('arrange');
});

it('scores an arrange question the student ordered correctly', function () {
    $result = app(ScoreAssessmentSnapshotsAction::class)->execute(
        arrangeSnapshot(),
        ['1' => ['order' => ['c', 'a', 'b']]],
    );

    expect($result['score'])->toBe(6)
        ->and($result['status'])->toBe('scored');
});

it('gives nothing for the wrong order', function () {
    // `['a','b','c']` is also the exact shape the player used to submit by
    // itself — the options in the order they were presented — so this is both
    // the wrong-answer case and the never-touched-it case.
    $result = app(ScoreAssessmentSnapshotsAction::class)->execute(
        arrangeSnapshot(),
        ['1' => ['order' => ['a', 'b', 'c']]],
    );

    expect($result['score'])->toBe(0);
});

it('would have scored full marks for doing nothing when the order was already right', function () {
    // The other half of the bug, and the more troubling one: an arrange
    // question whose correct order happens to be the presented order was
    // answered correctly by a student who never saw a control.
    $snapshot = arrangeSnapshot();
    $snapshot[0]['correct_answer'] = ['a', 'b', 'c'];

    $result = app(ScoreAssessmentSnapshotsAction::class)->execute(
        $snapshot,
        ['1' => ['order' => ['a', 'b', 'c']]],
    );

    expect($result['score'])->toBe(6);
});
