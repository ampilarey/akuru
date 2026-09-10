<?php

use App\Domains\Academics\Actions\AssignStudentToClassAction;
use App\Domains\Forms\Actions\ListFormResponsesAction;
use App\Domains\Forms\Actions\ListFormsForUserAction;
use App\Domains\Forms\Actions\SaveFormAction;
use App\Domains\Forms\Actions\SubmitFormResponseAction;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormResponse;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * E6a — sign-up sheets and surveys.
 *
 * A poll (E2b-b) answers one question inside a conversation. A form is what a
 * school sends for a trip: several questions, a window, and a results table
 * somebody has to work from.
 */
function makeForm(array $overrides = []): Form
{
    return app(SaveFormAction::class)->execute(array_merge([
        'title' => 'Trip sign-up',
        'fields' => [
            ['label' => 'Attending?', 'type' => 'yes_no', 'required' => true],
            ['label' => 'Dietary needs', 'type' => 'text'],
        ],
        'is_published' => true,
    ], $overrides), (int) User::factory()->create()->id);
}

it('refuses a form with no questions', function () {
    makeForm(['fields' => []]);
})->throws(ValidationException::class);

it('refuses a choice question with fewer than two options', function () {
    makeForm(['fields' => [['label' => 'Pick one', 'type' => 'select', 'options' => ['Only']]]]);
})->throws(ValidationException::class);

it('gives every question a stable key', function () {
    $form = makeForm();

    // The label is display; the key is identity, so fixing a typo in a label
    // must not orphan the answers already given under it.
    expect($form->fields[0]['key'])->not->toBeEmpty()
        ->and($form->fields[0]['key'])->not->toBe($form->fields[1]['key']);
});

it('offers a published form to its audience and hides it from others', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $student = makeStudent();
    app(AssignStudentToClassAction::class)->execute($class, (int) $student->id);

    makeForm(['target_classes' => [$class->id]]);

    $lister = app(ListFormsForUserAction::class);

    expect($lister->execute((int) $student->user_id, ['student']))->toHaveCount(1)
        ->and($lister->execute((int) makeStudent()->user_id, ['student']))->toBeEmpty();
});

it('hides an unpublished form from everyone', function () {
    makeForm(['is_published' => false]);

    expect(app(ListFormsForUserAction::class)->execute((int) User::factory()->create()->id, []))
        ->toBeEmpty();
});

it('records an answer and lets it be corrected', function () {
    $form = makeForm();
    $user = User::factory()->create();
    $keys = collect($form->fields)->pluck('key');

    $submit = app(SubmitFormResponseAction::class);
    $submit->execute((int) $form->id, (int) $user->id, [$keys[0] => 'yes', $keys[1] => 'None'], []);
    $submit->execute((int) $form->id, (int) $user->id, [$keys[0] => 'no', $keys[1] => 'Nuts'], []);

    // Answering again replaces: a parent who mis-taps corrects it, and the
    // results table still has one row per family.
    expect(FormResponse::query()->count())->toBe(1)
        ->and(FormResponse::query()->first()->answers[$keys[0]])->toBe('no');
});

it('refuses a submission that leaves a required question empty', function () {
    $form = makeForm();
    $keys = collect($form->fields)->pluck('key');

    app(SubmitFormResponseAction::class)
        ->execute((int) $form->id, (int) User::factory()->create()->id, [$keys[1] => 'None'], []);
})->throws(ValidationException::class);

it('discards an option that was never offered', function () {
    $form = makeForm(['fields' => [
        ['label' => 'Coach or own transport?', 'type' => 'select', 'options' => ['Coach', 'Own']],
    ]]);
    $key = $form->fields[0]['key'];

    app(SubmitFormResponseAction::class)
        ->execute((int) $form->id, (int) User::factory()->create()->id, [$key => 'Helicopter'], []);

    // A hand-posted value must not land in the results table where somebody
    // would act on it.
    expect(FormResponse::query()->first()->answers[$key])->toBeNull();
});

it('keeps only the offered options on a multi-select', function () {
    $form = makeForm(['fields' => [
        ['label' => 'Which days?', 'type' => 'multi_select', 'options' => ['Mon', 'Tue']],
    ]]);
    $key = $form->fields[0]['key'];

    app(SubmitFormResponseAction::class)
        ->execute((int) $form->id, (int) User::factory()->create()->id, [$key => ['Mon', 'Sat']], []);

    expect(FormResponse::query()->first()->answers[$key])->toBe(['Mon']);
});

it('refuses a submission to a form that is not for you', function () {
    $year = makeYear(['name' => '2026-2027', 'is_current' => true, 'status' => 'active']);
    $class = makeClass($year);
    $form = makeForm(['target_classes' => [$class->id]]);

    app(SubmitFormResponseAction::class)
        ->execute((int) $form->id, (int) User::factory()->create()->id, [], []);
})->throws(ValidationException::class);

it('refuses a submission once the form has closed', function () {
    $form = makeForm(['closes_at' => now()->subHour()]);

    app(SubmitFormResponseAction::class)
        ->execute((int) $form->id, (int) User::factory()->create()->id, [], []);
})->throws(ValidationException::class);

it('stores no person id on an anonymous form', function () {
    $form = makeForm(['is_anonymous' => true]);
    $keys = collect($form->fields)->pluck('key');
    $user = User::factory()->create();

    app(SubmitFormResponseAction::class)
        ->execute((int) $form->id, (int) $user->id, [$keys[0] => 'yes'], []);

    // Anonymous means no id at all, not a hidden one — anything else is a
    // promise the schema cannot keep.
    expect(FormResponse::query()->first()->user_id)->toBeNull();
});

it('cannot say whether you answered an anonymous form', function () {
    $form = makeForm(['is_anonymous' => true]);
    $keys = collect($form->fields)->pluck('key');
    $user = User::factory()->create();

    app(SubmitFormResponseAction::class)->execute((int) $form->id, (int) $user->id, [$keys[0] => 'yes'], []);

    expect(app(ListFormsForUserAction::class)->execute((int) $user->id, [])->first()['answered_at'])
        ->toBeNull();
});

it('records a second anonymous submission because it cannot tell', function () {
    $form = makeForm(['is_anonymous' => true]);
    $keys = collect($form->fields)->pluck('key');
    $user = User::factory()->create();

    $submit = app(SubmitFormResponseAction::class);
    $submit->execute((int) $form->id, (int) $user->id, [$keys[0] => 'yes'], []);
    $submit->execute((int) $form->id, (int) $user->id, [$keys[0] => 'no'], []);

    // The documented cost of the anonymity promise, asserted rather than left
    // as a surprise.
    expect(FormResponse::query()->count())->toBe(2);
});

it('freezes the questions once someone has answered', function () {
    $form = makeForm();
    $keys = collect($form->fields)->pluck('key');
    app(SubmitFormResponseAction::class)
        ->execute((int) $form->id, (int) User::factory()->create()->id, [$keys[0] => 'yes'], []);

    app(SaveFormAction::class)->execute([
        'title' => 'Trip sign-up (revised)',
        'fields' => [['label' => 'Completely different question', 'type' => 'text']],
        'is_published' => true,
    ], (int) $form->created_by, $form);

    // The title may be corrected; the questions may not, because rewording
    // them would silently change what past answers meant.
    expect($form->refresh()->title)->toBe('Trip sign-up (revised)')
        ->and($form->fields)->toHaveCount(2)
        ->and($form->fields[0]['label'])->toBe('Attending?');
});

it('omits the respondent from anonymous results', function () {
    $form = makeForm(['is_anonymous' => true]);
    $keys = collect($form->fields)->pluck('key');
    app(SubmitFormResponseAction::class)
        ->execute((int) $form->id, (int) User::factory()->create()->id, [$keys[0] => 'yes'], []);

    $results = app(ListFormResponsesAction::class)->execute((int) $form->id);

    // Absent, not blank: a column of dashes invites someone to go looking.
    expect($results['rows']->first())->not->toHaveKey('respondent')
        ->and($results['form']['responses'])->toBe(1);
});

it('names the respondent on a named form', function () {
    $form = makeForm();
    $keys = collect($form->fields)->pluck('key');
    $user = User::factory()->create();
    app(SubmitFormResponseAction::class)->execute((int) $form->id, (int) $user->id, [$keys[0] => 'yes'], []);

    expect(app(ListFormResponsesAction::class)->execute((int) $form->id)['rows']->first()['respondent'])
        ->toBe($user->name);
});
