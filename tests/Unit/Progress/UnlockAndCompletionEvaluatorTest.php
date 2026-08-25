<?php

use App\Domains\Progress\Actions\EvaluateCourseCompletionAction;
use App\Domains\Progress\Actions\EvaluateLessonUnlockAction;

it('unlocks the first required lesson and locks the next until the previous is complete', function () {
    $unlock = app(EvaluateLessonUnlockAction::class);
    $required = [10, 20, 30];

    expect($unlock->execute(10, $required, []))->toBeTrue()
        ->and($unlock->execute(20, $required, []))->toBeFalse()
        ->and($unlock->execute(20, $required, [10]))->toBeTrue()
        ->and($unlock->execute(30, $required, [10]))->toBeFalse()
        ->and($unlock->execute(30, $required, [10, 20]))->toBeTrue()
        ->and($unlock->execute(99, $required, [10, 20, 30]))->toBeFalse()
        ->and($unlock->execute(20, $required, [], true))->toBeTrue();
});

it('computes completion from required lessons and optional required sessions', function () {
    $eval = app(EvaluateCourseCompletionAction::class);

    expect($eval->execute([], []))->toMatchArray([
        'completed_required' => 0,
        'total_required' => 0,
        'percentage' => 0,
        'is_complete' => false,
    ]);

    expect($eval->execute([1, 2], [1]))->toMatchArray([
        'completed_required' => 1,
        'total_required' => 2,
        'percentage' => 50,
        'is_complete' => false,
    ]);

    expect($eval->execute([1, 2], [1, 2], [100], []))->toMatchArray([
        'completed_required' => 2,
        'total_required' => 3,
        'percentage' => 66,
        'is_complete' => false,
    ]);

    expect($eval->execute([1, 2], [1, 2], [100], [100]))->toMatchArray([
        'completed_required' => 3,
        'total_required' => 3,
        'percentage' => 100,
        'is_complete' => true,
    ]);
});
