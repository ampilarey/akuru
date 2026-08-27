<?php

use App\Domains\Progress\Actions\EvaluateCourseCompletionAction;
use App\Domains\Progress\Actions\EvaluateLessonUnlockAction;
use App\Domains\Progress\Contracts\CourseCompletionEvaluator;
use App\Domains\Progress\Contracts\LessonUnlockEvaluator;

it('binds the default sequential unlock and required-count completion strategies', function () {
    expect(app(LessonUnlockEvaluator::class))->toBeInstanceOf(EvaluateLessonUnlockAction::class)
        ->and(app(CourseCompletionEvaluator::class))->toBeInstanceOf(EvaluateCourseCompletionAction::class);
});

it('lets a different pedagogy be bound without touching the engine', function () {
    // ADR-022: this is the property ROADMAP §2a promises. Phase F (Hifz → engine)
    // binds a milestone-based completion rule exactly like this rather than
    // teaching Courses/Progress what a milestone is (rule 6).
    app()->bind(CourseCompletionEvaluator::class, fn () => new class implements CourseCompletionEvaluator
    {
        public function execute(
            array $requiredLessonIds,
            array $completedLessonIds,
            array $requiredSessionIds = [],
            array $attendedSessionIds = [],
        ): array {
            // "Everything is complete the moment anything is" — deliberately
            // unlike the default, so the swap is unmistakable.
            return [
                'completed_required' => 1,
                'total_required' => 1,
                'percentage' => 100,
                'is_complete' => $completedLessonIds !== [],
            ];
        }
    });

    $result = app(CourseCompletionEvaluator::class)->execute([1, 2, 3], [1]);

    expect($result['is_complete'])->toBeTrue()
        ->and($result['percentage'])->toBe(100);

    // The default would have reported 1 of 3.
    $default = app(EvaluateCourseCompletionAction::class)->execute([1, 2, 3], [1]);
    expect($default['is_complete'])->toBeFalse()
        ->and($default['completed_required'])->toBe(1)
        ->and($default['total_required'])->toBe(3);
});

it('keeps the default unlock sequential and preview-open', function () {
    $unlock = app(LessonUnlockEvaluator::class);

    expect($unlock->execute(3, [1, 2, 3], [1]))->toBeFalse()
        ->and($unlock->execute(2, [1, 2, 3], [1]))->toBeTrue()
        ->and($unlock->execute(3, [1, 2, 3], [], isPreview: true))->toBeTrue();
});
