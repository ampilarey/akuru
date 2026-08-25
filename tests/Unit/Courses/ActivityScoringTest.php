<?php

use App\Domains\Courses\Actions\NormalizeTextAnswerAction;
use App\Domains\Courses\Actions\ScoreActivityAnswersAction;

it('normalizes text with only the configured flags', function () {
    $normalize = app(NormalizeTextAnswerAction::class);

    expect($normalize->execute('  Hello,  World!  ', [
        'trim' => true,
        'collapse_space' => true,
        'strip_punctuation' => true,
        'case_insensitive' => true,
    ]))->toBe('hello world');

    expect($normalize->execute('كَتَبَ', [
        'strip_tashkeel' => false,
        'normalize_alef' => false,
    ]))->toBe('كَتَبَ')
        ->and($normalize->execute('كَتَبَ', ['strip_tashkeel' => true]))->toBe('كتب')
        ->and($normalize->execute('أحمد', ['normalize_alef' => true]))->toBe('احمد');
});

it('scores the four activity patterns', function () {
    $score = app(ScoreActivityAnswersAction::class);

    expect($score->execute([
        'pattern' => 'selection',
        'max_score' => 10,
        'data' => ['correct_ids' => ['b', 'a']],
    ], ['selected_ids' => ['a', 'b']]))->toMatchArray(['score' => 10, 'status' => 'scored', 'passed' => true]);

    expect($score->execute([
        'pattern' => 'selection',
        'max_score' => 10,
        'data' => ['correct_ids' => ['a']],
    ], ['selected_ids' => ['b']]))->toMatchArray(['score' => 0, 'status' => 'scored', 'passed' => false]);

    expect($score->execute([
        'pattern' => 'text_input',
        'max_score' => 5,
        'data' => ['acceptable' => ['Salam']],
        'settings' => ['normalize' => ['case_insensitive' => true, 'trim' => true]],
    ], ['text' => '  salam ']))->toMatchArray(['score' => 5, 'status' => 'scored']);

    expect($score->execute([
        'pattern' => 'arrange',
        'max_score' => 4,
        'data' => ['correct_order' => ['1', '2', '3']],
    ], ['order' => ['1', '2', '3']]))->toMatchArray(['score' => 4, 'status' => 'scored']);

    expect($score->execute([
        'pattern' => 'teacher_marked',
        'max_score' => 20,
        'data' => ['prompt' => 'Write'],
    ], ['text' => 'draft']))->toMatchArray(['score' => 0, 'status' => 'submitted', 'passed' => false]);
});
