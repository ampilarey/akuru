<?php

use App\Domains\Courses\Actions\SaveQuestionAction;
use App\Domains\Courses\Actions\SnapshotQuestionAction;
use App\Domains\Courses\Enums\ActivityPattern;
use App\Domains\Courses\Enums\QuestionType;
use App\Domains\Courses\Models\CourseSubject;
use App\Domains\Courses\Models\Question;
use App\Domains\ExamsGrades\Actions\ListStandardTagsAction;
use App\Domains\ExamsGrades\Actions\SyncStandardTagsAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('maps each question type onto one of the four activity patterns', function () {
    expect(QuestionType::McqSingle->pattern())->toBe(ActivityPattern::Selection)
        ->and(QuestionType::TrueFalse->pattern())->toBe(ActivityPattern::Selection)
        ->and(QuestionType::FillBlank->pattern())->toBe(ActivityPattern::TextInput)
        ->and(QuestionType::Arrange->pattern())->toBe(ActivityPattern::Arrange)
        ->and(QuestionType::Essay->pattern())->toBe(ActivityPattern::TeacherMarked)
        ->and(QuestionType::FileSubmission->pattern())->toBe(ActivityPattern::TeacherMarked);
});

it('creates reusable questions and rejects unknown types', function () {
    $admin = actingPeopleAdmin(['courses.manage']);
    $subjectId = CourseSubject::query()->where('slug', 'nahw')->value('id');

    $question = app(SaveQuestionAction::class)->execute([
        'subject_id' => $subjectId,
        'question_type' => 'mcq_single',
        'title' => 'Kitab',
        'question_text' => 'What does kitab mean?',
        'options' => [['id' => 'a', 'label' => 'Book'], ['id' => 'b', 'label' => 'Pen']],
        'correct_answer' => ['a'],
        'created_by' => $admin->id,
    ]);

    expect($question->pattern)->toBe(ActivityPattern::Selection)
        ->and($question->question_type)->toBe(QuestionType::McqSingle)
        ->and($question->subject_id)->toBe($subjectId);

    expect(fn () => app(SaveQuestionAction::class)->execute([
        'question_type' => 'custom_engine',
        'question_text' => 'Nope',
    ]))->toThrow(ValidationException::class);
});

it('does not mutate a stored snapshot when the live question is edited', function () {
    $question = app(SaveQuestionAction::class)->execute([
        'question_type' => 'short_answer',
        'question_text' => 'Type the word',
        'acceptable_answers' => ['kitab'],
    ]);

    $snapshot = app(SnapshotQuestionAction::class)->execute($question);

    app(SaveQuestionAction::class)->execute([
        'question_type' => 'short_answer',
        'question_text' => 'Type a different word',
        'acceptable_answers' => ['qalam'],
    ], $question->fresh());

    expect($snapshot['question_text'])->toBe('Type the word')
        ->and($snapshot['acceptable_answers'])->toBe(['kitab'])
        ->and($question->fresh()->question_text)->toBe('Type a different word')
        ->and($snapshot['question_text'])->not->toBe($question->fresh()->question_text);
});

it('tags questions through the ExamsGrades contract without importing its models', function () {
    Schema::create('standard_taggables', function ($table) {
        $table->id();
        $table->unsignedBigInteger('standard_id');
        $table->string('taggable_type');
        $table->unsignedBigInteger('taggable_id');
        $table->timestamps();
    });

    $question = app(SaveQuestionAction::class)->execute([
        'question_type' => 'true_false',
        'question_text' => 'Arabic is a language',
        'options' => [['id' => 'true', 'label' => 'True'], ['id' => 'false', 'label' => 'False']],
        'correct_answer' => ['true'],
        'standard_ids' => [7, 9],
    ]);

    expect(app(ListStandardTagsAction::class)->execute('question', $question->id)->all())->toBe([7, 9]);

    app(SyncStandardTagsAction::class)->execute('question', $question->id, [9]);
    expect(app(ListStandardTagsAction::class)->execute('question', $question->id)->all())->toBe([9]);

    $source = file_get_contents(app_path('Domains/Courses/Actions/SaveQuestionAction.php'));
    expect($source)->not->toContain('App\\Domains\\ExamsGrades\\Models\\')
        ->and($source)->toContain('SyncStandardTagsAction');
});

it('stores attachments through Media and exposes catalog CRUD plus CSV', function () {
    Storage::fake('local');
    $admin = actingPeopleAdmin(['courses.manage']);

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->post(route('catalog.questions.store'), [
            'question_type' => 'image',
            'question_text' => 'Choose the picture',
            'options' => json_encode([['id' => 'a', 'label' => 'A'], ['id' => 'b', 'label' => 'B']]),
            'correct_answer' => json_encode(['a']),
            'file' => UploadedFile::fake()->image('prompt.jpg'),
        ])
        ->assertRedirect(route('catalog.questions.index'));

    $question = Question::query()->first();
    expect($question?->attachments[0]['media_id'] ?? null)->toBeInt()
        ->and($question?->pattern->value)->toBe('selection');

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.questions.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Courses/Catalog/Questions')
            ->has('rows', 1)
        );

    $this->withoutLocalizationMiddleware()
        ->actingAs($admin)
        ->get(route('catalog.questions.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $other = actingPeopleAdmin(['hr.manage']);
    $this->withoutLocalizationMiddleware()
        ->actingAs($other)
        ->get(route('catalog.questions.index'))
        ->assertForbidden();
});
