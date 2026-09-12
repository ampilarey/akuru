<?php

use App\Domains\Courses\Actions\NormalizeTextAnswerAction;
use App\Domains\Courses\Actions\SaveQuestionAction;
use App\Domains\Courses\Actions\ScoreActivityAnswersAction;
use App\Domains\Courses\Actions\ValidateNormalizationSettingsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

/**
 * SPEC §18: "For auto-marked text input, comparison must be configurable per
 * activity", listing general switches, **strict mode**, **lenient mode**, and
 * Arabic-specific switches — with the rule that "Arabic normalization must not
 * be global. It should apply only when the activity configuration requires
 * it."
 *
 * The switches existed and the scorer read them. Neither mode existed, nothing
 * validated what was stored, and no screen anywhere could set any of it — so
 * every text question in the product was marked on the lenient defaults.
 */
uses(RefreshDatabase::class);

function normalize(string $value, array $settings = []): string
{
    return app(NormalizeTextAnswerAction::class)->execute($value, $settings);
}

it('keeps the historical defaults when nothing is configured', function () {
    // Questions saved before modes existed must not change how they mark.
    expect(normalize('  Hello   World  '))->toBe('hello world');
});

it('forgives nothing in strict mode', function () {
    // §18's own example: "A formula answer may use strict matching." On the
    // defaults `mL` and `ml` compared equal, with no way to say otherwise.
    expect(normalize('  mL  x2 ', ['mode' => 'strict']))->toBe('  mL  x2 ');
});

it('forgives punctuation and case in lenient mode', function () {
    expect(normalize('  The  Answer, please! ', ['mode' => 'lenient']))->toBe('the answer please');
});

it('lets one switch override the mode', function () {
    // "Strict, but tolerate a trailing space" is one setting, not a full
    // enumeration of every flag.
    expect(normalize('  mL  ', ['mode' => 'strict', 'trim' => true]))->toBe('mL');
});

it('leaves Arabic normalization off in lenient mode', function () {
    // §18: "Arabic normalization must not be global." A mode that quietly
    // switched it on would be exactly that.
    $word = 'أَلِف';

    expect(normalize($word, ['mode' => 'lenient']))->toBe($word);
});

it('does not strip tashkeel when removing punctuation', function () {
    // Found by the test above, and pre-dating modes entirely. The punctuation
    // regex kept only letters, numbers and spaces — and an Arabic haraka is
    // none of those, so "remove punctuation" quietly did the Arabic
    // normalization §18 says must not be global. An Arabic diacritics question
    // that ticked this general switch accepted a bare answer as correct, which
    // is the single thing that question exists to reject.
    expect(normalize('أَلِف!', ['strip_punctuation' => true, 'case_insensitive' => false]))
        ->toBe('أَلِف');
});

it('still removes punctuation and symbols', function () {
    expect(normalize('the answer, please! (really) 50%', ['strip_punctuation' => true]))
        ->toBe('the answer please really 50');
});

it('applies Arabic switches only when asked', function () {
    expect(normalize('أَلِف', ['strip_tashkeel' => true]))->toBe('ألف')
        ->and(normalize('ألف', ['normalize_alef' => true]))->toBe('الف')
        ->and(normalize('مؤمن', ['normalize_hamza' => true]))->toBe('مءمن')
        ->and(normalize('مدرسة', ['taa_marbuta' => true]))->toBe('مدرسه');
});

it('scores a strict question against an answer that only differs in case', function () {
    $activity = [
        'pattern' => 'text_input',
        'max_score' => 4,
        'data' => ['acceptable' => ['mL']],
        'settings' => ['normalize' => ['mode' => 'strict']],
    ];

    expect(app(ScoreActivityAnswersAction::class)->execute($activity, ['text' => 'ml'])['score'])->toBe(0)
        ->and(app(ScoreActivityAnswersAction::class)->execute($activity, ['text' => 'mL'])['score'])->toBe(4);
});

it('rejects a misspelled switch instead of storing and ignoring it', function () {
    // The case that matters: `strict_tashkeel` was accepted, saved, and then
    // ignored by the normalizer. The question marked leniently while its
    // settings said it did not, and nothing reported a problem.
    expect(fn () => app(ValidateNormalizationSettingsAction::class)->execute(['strict_tashkeel' => true]))
        ->toThrow(ValidationException::class);
});

it('rejects an unknown mode', function () {
    expect(fn () => app(ValidateNormalizationSettingsAction::class)->execute(['mode' => 'forgiving']))
        ->toThrow(ValidationException::class);
});

it('rejects a non-boolean switch', function () {
    expect(fn () => app(ValidateNormalizationSettingsAction::class)->execute(['trim' => 'sometimes']))
        ->toThrow(ValidationException::class);
});

it('accepts the checkbox and form encodings of true and false', function () {
    $clean = app(ValidateNormalizationSettingsAction::class)->execute([
        'mode' => 'strict',
        'trim' => '1',
        'case_insensitive' => 'false',
    ]);

    expect($clean)->toBe(['mode' => 'strict', 'trim' => true, 'case_insensitive' => false]);
});

it('stores nothing for an empty settings object', function () {
    expect(app(ValidateNormalizationSettingsAction::class)->execute([]))->toBeNull()
        ->and(app(ValidateNormalizationSettingsAction::class)->execute(null))->toBeNull();
});

it('saves normalization settings on a question', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $question = app(SaveQuestionAction::class)->execute([
        'question_type' => 'short_answer',
        'question_text' => 'Write the unit',
        'correct_answer' => ['mL'],
        'normalization_settings' => ['mode' => 'strict'],
        'created_by' => $admin->id,
    ]);

    expect($question->normalization_settings)->toBe(['mode' => 'strict']);
});

it('refuses to save a question carrying an unknown switch', function () {
    $admin = actingPeopleAdmin(['courses.manage']);

    expect(fn () => app(SaveQuestionAction::class)->execute([
        'question_type' => 'short_answer',
        'question_text' => 'Write the unit',
        'correct_answer' => ['mL'],
        'normalization_settings' => ['case_insenitive' => true],
        'created_by' => $admin->id,
    ]))->toThrow(ValidationException::class);
});

it('accepts acceptable answers one per line, not only as JSON', function () {
    // `jsonList` turned any string that failed to decode into `[]`, so a
    // plainly-typed list was saved as no accepted answers at all and the
    // question then marked only its single `correct_answer`.
    $admin = actingPeopleAdmin(['courses.manage']);
    $question = app(SaveQuestionAction::class)->execute([
        'question_type' => 'short_answer',
        'question_text' => 'Name a letter',
        'correct_answer' => ['alif'],
        'acceptable_answers' => "alif\nalef\n\n  aleph  ",
        'created_by' => $admin->id,
    ]);

    expect($question->acceptable_answers)->toBe(['alif', 'alef', 'aleph']);
});

it('still accepts acceptable answers as a JSON array', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $question = app(SaveQuestionAction::class)->execute([
        'question_type' => 'short_answer',
        'question_text' => 'Name a letter',
        'correct_answer' => ['alif'],
        'acceptable_answers' => '["alif","alef"]',
        'created_by' => $admin->id,
    ]);

    expect($question->acceptable_answers)->toBe(['alif', 'alef']);
});

it('offers the modes and switches to the builder', function () {
    // The builder must not re-derive which question types are text input, nor
    // hardcode the switch list — both would drift the moment a type is added.
    $admin = actingPeopleAdmin(['courses.manage']);

    $this->actingAs($admin)
        ->withoutLocalizationMiddleware()
        ->get('/catalog/questions')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('textInputTypes', ['fill_blank', 'short_answer'])
            ->where('normalizationModes', ['strict', 'lenient'])
            ->has('normalizationFlags', 8));
});
