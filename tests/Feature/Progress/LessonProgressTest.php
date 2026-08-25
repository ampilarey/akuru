<?php

use App\Domains\Progress\Actions\CalculateCourseProgressAction;
use App\Domains\Progress\Actions\RecordLessonProgressAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('calculates required-lesson course progress', function () {
    $calc = app(CalculateCourseProgressAction::class);

    expect($calc->execute(0, 0))->toBe(0)
        ->and($calc->execute(1, 2))->toBe(50)
        ->and($calc->execute(2, 3))->toBe(66)
        ->and($calc->execute(3, 3))->toBe(100);
});

it('refuses progress without a lesson revision id', function () {
    expect(fn () => app(RecordLessonProgressAction::class)->execute([
        'enrollment_id' => 1,
        'course_id' => 1,
        'course_module_id' => 1,
        'lesson_id' => 1,
        'student_id' => 1,
        'status' => 'in_progress',
    ]))->toThrow(ValidationException::class);
});
