<?php

use App\Domains\Courses\Actions\SaveActivityAction;
use App\Domains\Courses\Actions\SaveEngineCourseAction;
use App\Domains\Courses\Components\Arabic\Models\ArabicHarakah;
use App\Domains\Courses\Components\Arabic\Models\ArabicLetter;
use App\Domains\Courses\Models\CourseSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('stores listening and speaking skills as metadata on the four patterns', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $course = app(SaveEngineCourseAction::class)->execute([
        'title' => 'Arabic skills',
        'subject_id' => CourseSubject::query()->where('slug', 'arabic')->value('id'),
        'created_by' => $admin->id,
    ]);
    $baa = ArabicLetter::query()->where('key_name', 'baa')->firstOrFail();
    $fatha = ArabicHarakah::query()->where('key_name', 'fatha')->firstOrFail();

    $listening = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Hear baa',
        'pattern' => 'selection',
        'activity_type' => 'listen_and_choose',
        'data' => [
            'prompt' => 'Choose the heard letter',
            'options' => [['id' => 'baa', 'label' => 'ب'], ['id' => 'taa', 'label' => 'ت']],
            'correct_ids' => ['baa'],
        ],
        'settings' => [
            'skill' => 'listening',
            'letter_id' => $baa->id,
            'harakah_id' => $fatha->id,
        ],
    ]);

    $speaking = app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Say baa',
        'pattern' => 'teacher_marked',
        'activity_type' => 'letter_reading',
        'data' => ['prompt' => 'Read this letter', 'submission_kind' => 'file'],
        'settings' => [
            'skill' => 'speaking',
            'letter_id' => $baa->id,
            'teacher_review_required' => true,
        ],
    ]);

    expect($listening->settings['skill'])->toBe('listening')
        ->and($listening->settings['letter_id'])->toBe($baa->id)
        ->and($listening->pattern->value)->toBe('selection')
        ->and($speaking->settings['skill'])->toBe('speaking')
        ->and($speaking->pattern->value)->toBe('teacher_marked');

    expect(fn () => app(SaveActivityAction::class)->execute([
        'course_id' => $course->id,
        'title' => 'Bad letter',
        'pattern' => 'text_input',
        'data' => ['prompt' => 'Type', 'acceptable' => ['ب']],
        'settings' => ['skill' => 'writing', 'letter_id' => 999999],
    ]))->toThrow(ValidationException::class);
});
