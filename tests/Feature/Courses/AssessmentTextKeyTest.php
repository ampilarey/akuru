<?php

use App\Domains\Courses\Actions\ScoreAssessmentSnapshotsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The question builder has two boxes for a text answer: "correct answer"
 * (`correct_answer`, the obvious one) and "other accepted answers"
 * (`acceptable_answers`). The assessment scorer read only the second, so a
 * short-answer question whose author filled in the correct answer and left
 * the alternatives blank could never be marked right. The assessment walk
 * typed "male" against a key of "Male" and was scored 1/2 (Phase 2 audit
 * D2, STATUS §5fi). Activities were never affected — their builder writes
 * `acceptable` directly.
 */
function textSnapshot(array $overrides = []): array
{
    return array_merge([
        'question_id' => 7,
        'points' => 1,
        'pattern' => 'text_input',
        'options' => [],
        'correct_answer' => ['Male'],
        'acceptable_answers' => [],
        'normalization_settings' => null,
    ], $overrides);
}

it('scores a text answer against the correct answer, not only the alternatives', function () {
    $score = fn (array $snapshot, string $text) => app(ScoreAssessmentSnapshotsAction::class)
        ->execute([$snapshot], ['7' => ['text' => $text]], null, false)['score'];

    // The key alone, matched leniently (the default is case-insensitive).
    expect($score(textSnapshot(), 'male'))->toBe(1)
        ->and($score(textSnapshot(), 'Hulhumale'))->toBe(0)
        // A single string on an older row still counts.
        ->and($score(textSnapshot(['correct_answer' => 'Male']), 'MALE'))->toBe(1)
        // Alternatives still work, alongside the key.
        ->and($score(textSnapshot(['acceptable_answers' => ["Male'"]]), "male'"))->toBe(1)
        ->and($score(textSnapshot(['acceptable_answers' => ["Male'"]]), 'male'))->toBe(1)
        // Nothing to match against scores nothing, rather than everything.
        ->and($score(textSnapshot(['correct_answer' => [], 'acceptable_answers' => []]), ''))->toBe(0);
});
